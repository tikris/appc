<?php
declare(strict_types=1);

namespace Appc;

/** Côté participants : saisie du code, liste des idées, votes, notes de contexte. */
final class Portal
{
    private const FLASH = [
        'idea_added'   => 'Votre proposition a été publiée.',
        'idea_updated' => 'Proposition modifiée.',
        'idea_deleted' => 'Proposition supprimée.',
        'voted'        => 'Votre vote a été enregistré.',
        'note_saved'   => 'Note de contexte enregistrée.',
        'note_deleted' => 'Note supprimée.',
    ];

    public function __construct(private Instance $in)
    {
    }

    // ── Saisie du code ──────────────────────────────────────────────────────

    public function access(): never
    {
        $in = $this->in;
        if (Access::participant($in) !== null) {
            Http::redirect(url($in->slug . '/'));
        }
        $error = null;
        $code = is_string($_GET['code'] ?? null) ? clean_text($_GET['code']) : '';
        if (Http::method() === 'POST') {
            Csrf::check();
            $code = clean_text($_POST['code'] ?? '');
            $error = Access::join($in, $code);
            if ($error === null) {
                Http::redirect(url($in->slug . '/'));
            }
        }
        render('access', [
            'in'        => $in,
            'pageTitle' => $in->name(),
            'heading'   => $in->name(),
            'error'     => $error,
            'prefill'   => mb_substr($code, 0, 20),
        ], $error === null ? 200 : ($error === Access::MSG_BLOCKED ? 429 : 401));
    }

    public function leave(): never
    {
        if (Http::method() !== 'POST') {
            abort(404, 'Page introuvable.');
        }
        Csrf::check();
        Access::leave();
        Http::redirect(url($this->in->slug . '/acces'));
    }

    // ── Portail ─────────────────────────────────────────────────────────────

    public function index(): never
    {
        $in = $this->in;
        $me = Access::participant($in);
        $admin = Access::isAdmin($in);
        if ($me === null && !$admin) {
            Http::redirect(url($in->slug . '/acces'));
        }
        $repo = new Repo($in->db);
        $ctx = null;
        if (Http::method() === 'POST') {
            Csrf::check();
            $ctx = $this->post($repo, $me, $admin);
        }
        $this->show($repo, $me, $admin, $ctx);
    }

    /** Traite une action. Redirige en cas de succès ; retourne le contexte d'erreur du formulaire sinon. */
    private function post(Repo $repo, ?array $me, bool $admin): array
    {
        $in = $this->in;
        $themeFilter = (int) ($_POST['theme'] ?? 0);
        $done = static function (string $flash, ?int $ideaId = null) use ($in, $themeFilter): never {
            $q = '?m=' . $flash . ($themeFilter > 0 ? '&theme=' . $themeFilter : '');
            Http::redirect(url($in->slug . '/') . $q . ($ideaId !== null ? '#idea-' . $ideaId : ''));
        };
        $myId = $me !== null ? (int) $me['id'] : null;

        switch ((string) ($_POST['action'] ?? '')) {
            case 'add_idea':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut proposer une idée.');
                }
                if (!$in->isOpen()) {
                    abort(403, $in->notOpenMessage());
                }
                $old = $this->ideaInput();
                $error = $this->validateIdea($repo, $old);
                if ($error === null && $repo->countIdeasBy($myId) >= $in->maxIdeas()) {
                    $error = sprintf('Vous avez atteint la limite de %d propositions par participant.', $in->maxIdeas());
                }
                if ($error !== null) {
                    return ['form' => 'add', 'error' => $error, 'old' => $old, 'status' => 422];
                }
                $id = $repo->addIdea($old['theme_id'], $old['title'], $old['description'], $myId);
                $done('idea_added', $id);

                // no break : $done() ne retourne jamais
            case 'edit_idea':
                $idea = $repo->idea((int) ($_POST['idea_id'] ?? 0)) ?? abort(404, 'Proposition introuvable.');
                if ($myId === null || (int) $idea['participant_id'] !== $myId) {
                    abort(403, 'Vous ne pouvez modifier que vos propres propositions.');
                }
                if (!$in->isOpen()) {
                    abort(403, $in->notOpenMessage());
                }
                $old = $this->ideaInput();
                $error = $this->validateIdea($repo, $old);
                if ($error !== null) {
                    return ['form' => 'edit:' . $idea['id'], 'error' => $error, 'old' => $old, 'status' => 422];
                }
                $repo->updateIdea((int) $idea['id'], $old['theme_id'], $old['title'], $old['description']);
                $done('idea_updated', (int) $idea['id']);

            case 'delete_idea':
                $idea = $repo->idea((int) ($_POST['idea_id'] ?? 0)) ?? abort(404, 'Proposition introuvable.');
                $isOwner = $myId !== null && (int) $idea['participant_id'] === $myId;
                if (!$admin && !($isOwner && $in->isOpen())) {
                    abort(403, 'Suppression non autorisée.');
                }
                $repo->deleteIdea((int) $idea['id']);
                $done('idea_deleted');

            case 'vote':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut voter.');
                }
                if (!$in->isOpen()) {
                    abort(403, $in->notOpenMessage());
                }
                $idea = $repo->idea((int) ($_POST['idea_id'] ?? 0)) ?? abort(404, 'Proposition introuvable.');
                if ((int) $idea['participant_id'] === $myId) {
                    abort(403, 'Vous ne pouvez pas voter pour votre propre proposition.');
                }
                $repo->vote((int) $idea['id'], $myId);
                $done('voted', (int) $idea['id']);

            case 'save_note':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut ajouter une note.');
                }
                $idea = $repo->idea((int) ($_POST['idea_id'] ?? 0)) ?? abort(404, 'Proposition introuvable.');
                $body = clean_text($_POST['body'] ?? '');
                $error = null;
                if ($body === '') {
                    $error = 'La note ne peut pas être vide.';
                } elseif (mb_strlen($body) > $in->noteMax()) {
                    $error = sprintf('La note est limitée à %d caractères.', $in->noteMax());
                }
                if ($error !== null) {
                    return ['form' => 'note:' . $idea['id'], 'error' => $error, 'old' => ['body' => $body], 'status' => 422];
                }
                $repo->saveNote((int) $idea['id'], $myId, $body);
                $done('note_saved', (int) $idea['id']);

            case 'delete_note':
                $note = $repo->note((int) ($_POST['note_id'] ?? 0)) ?? abort(404, 'Note introuvable.');
                if (!$admin && !($myId !== null && (int) $note['participant_id'] === $myId)) {
                    abort(403, 'Suppression non autorisée.');
                }
                $repo->deleteNote((int) $note['id']);
                $done('note_deleted', (int) $note['idea_id']);
        }
        abort(400, 'Action inconnue.');
    }

    /** @return array{theme_id:int,title:string,description:string} */
    private function ideaInput(): array
    {
        return [
            'theme_id'    => (int) ($_POST['theme_id'] ?? 0),
            'title'       => clean_text($_POST['title'] ?? ''),
            'description' => clean_text($_POST['description'] ?? ''),
        ];
    }

    private function validateIdea(Repo $repo, array $v): ?string
    {
        $in = $this->in;
        if ($repo->theme($v['theme_id']) === null) {
            return 'Choisissez un thème.';
        }
        $len = mb_strlen($v['title']);
        if ($len < $in->titleMin() || $len > $in->titleMax()) {
            return sprintf('Le titre doit faire entre %d et %d caractères.', $in->titleMin(), $in->titleMax());
        }
        if (mb_strlen($v['description']) > $in->descMax()) {
            return sprintf('La description est limitée à %d caractères.', $in->descMax());
        }
        return null;
    }

    private function show(Repo $repo, ?array $me, bool $admin, ?array $ctx): never
    {
        $in = $this->in;
        $themes = $repo->themes();

        $active = null;
        $wanted = (int) ($_GET['theme'] ?? 0);
        foreach ($themes as $t) {
            if ((int) $t['id'] === $wanted) {
                $active = $t;
            }
        }

        $ideas = $repo->ideas($active !== null ? (int) $active['id'] : null, $me !== null ? (int) $me['id'] : 0);
        $counts = $repo->ideaCountsByTheme();

        $themeNumber = [];
        foreach ($themes as $i => $t) {
            $themeNumber[(int) $t['id']] = $i + 1;
        }

        $flashKey = is_string($_GET['m'] ?? null) ? $_GET['m'] : '';
        $form = $ctx['form'] ?? null;

        render('portal', [
            'in'          => $in,
            'pageTitle'   => $in->name(),
            'heading'     => $in->name(),
            'me'          => $me,
            'admin'       => $admin,
            'open'        => $in->isOpen(),
            'phase'       => $in->phase(),
            'themes'      => $themes,
            'themeNumber' => $themeNumber,
            'counts'      => $counts,
            'total'       => array_sum($counts),
            'active'      => $active,
            'ideas'       => $ideas,
            'notes'       => $repo->notesByIdea(array_column($ideas, 'id')),
            'flash'       => self::FLASH[$flashKey] ?? null,
            'form'        => $form,
            'formError'   => $ctx['error'] ?? null,
            'old'         => $ctx['old'] ?? [],
            'editId'      => str_starts_with((string) $form, 'edit:') ? (int) substr($form, 5) : (int) ($_GET['edit'] ?? 0),
            'noteId'      => str_starts_with((string) $form, 'note:') ? (int) substr($form, 5) : (int) ($_GET['note'] ?? 0),
        ], $ctx['status'] ?? 200);
    }
}
