<?php
declare(strict_types=1);

namespace Appc;

/** Page d'une consultation : liste des idées par thème, propositions, votes, notes de contexte. */
final class ConsultationPage
{
    /* Limites reprises du portail d'origine (/cc/) : titre 3 à 120, description facultative jusqu'à 400, note 600. */
    public const TITLE_MIN = 3;
    public const TITLE_MAX = 120;
    public const DESC_MAX = 400;
    public const NOTE_MAX = 600;

    private const FLASH = [
        'idea_added'   => 'Votre proposition a été publiée.',
        'idea_updated' => 'Proposition modifiée.',
        'idea_deleted' => 'Proposition supprimée.',
        'voted'        => 'Votre vote a été enregistré.',
        'note_saved'   => 'Note de contexte enregistrée.',
        'note_deleted' => 'Note supprimée.',
    ];

    public function __construct(private Instance $in, private Activity $a)
    {
    }

    /** Le jeton CSRF des POST a déjà été vérifié par Portal. */
    public function handle(?array $me, bool $admin): never
    {
        $repo = new Repo($this->in->db);
        $ctx = Http::method() === 'POST' ? $this->post($repo, $me, $admin) : null;
        $this->show($repo, $me, $admin, $ctx);
    }

    private function self(): string
    {
        return url($this->in->slug . '/activite/' . $this->a->id);
    }

    /** Traite une action. Redirige en cas de succès ; retourne le contexte d'erreur du formulaire sinon. */
    private function post(Repo $repo, ?array $me, bool $admin): array
    {
        $a = $this->a;
        $self = $this->self();
        $themeFilter = (int) ($_POST['theme'] ?? 0);
        $done = static function (string $flash, ?int $ideaId = null) use ($self, $themeFilter): never {
            $q = '?m=' . $flash . ($themeFilter > 0 ? '&theme=' . $themeFilter : '');
            Http::redirect($self . $q . ($ideaId !== null ? '#idea-' . $ideaId : ''));
        };
        $myId = $me !== null ? (int) $me['id'] : null;
        $findIdea = static fn(): array => $repo->idea($a->id, (int) ($_POST['idea_id'] ?? 0)) ?? abort(404, 'Proposition introuvable.');

        switch ((string) ($_POST['action'] ?? '')) {
            case 'add_idea':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut proposer une idée.');
                }
                if (!$a->isOpen()) {
                    abort(403, $a->notOpenMessage());
                }
                $old = $this->ideaInput();
                $error = $this->validateIdea($repo, $old);
                if ($error === null && $repo->countIdeasBy($a->id, $myId) >= $a->maxIdeas) {
                    $error = sprintf('Vous avez atteint la limite de %d propositions par participant.', $a->maxIdeas);
                }
                if ($error !== null) {
                    return ['form' => 'add', 'error' => $error, 'old' => $old, 'status' => 422];
                }
                $id = $repo->addIdea($a->id, $old['theme_id'], $old['title'], $old['description'], $myId);
                $done('idea_added', $id);

                // no break : $done() ne retourne jamais
            case 'edit_idea':
                $idea = $findIdea();
                if ($myId === null || (int) $idea['participant_id'] !== $myId) {
                    abort(403, 'Vous ne pouvez modifier que vos propres propositions.');
                }
                if (!$a->isOpen()) {
                    abort(403, $a->notOpenMessage());
                }
                $old = $this->ideaInput();
                $error = $this->validateIdea($repo, $old);
                if ($error !== null) {
                    return ['form' => 'edit:' . $idea['id'], 'error' => $error, 'old' => $old, 'status' => 422];
                }
                $repo->updateIdea((int) $idea['id'], $old['theme_id'], $old['title'], $old['description']);
                $done('idea_updated', (int) $idea['id']);

            case 'delete_idea':
                $idea = $findIdea();
                $isOwner = $myId !== null && (int) $idea['participant_id'] === $myId;
                if (!$admin && !($isOwner && $a->isOpen())) {
                    abort(403, 'Suppression non autorisée.');
                }
                $repo->deleteIdea((int) $idea['id']);
                $done('idea_deleted');

            case 'vote':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut voter.');
                }
                if (!$a->isOpen()) {
                    abort(403, $a->notOpenMessage());
                }
                $idea = $findIdea();
                if ((int) $idea['participant_id'] === $myId) {
                    abort(403, 'Vous ne pouvez pas voter pour votre propre proposition.');
                }
                $repo->vote((int) $idea['id'], $myId);
                $done('voted', (int) $idea['id']);

            case 'save_note':
                if ($myId === null) {
                    abort(403, 'Seul un participant disposant d\'un code peut ajouter une note.');
                }
                $idea = $findIdea();
                $body = clean_text($_POST['body'] ?? '');
                $error = null;
                if ($body === '') {
                    $error = 'La note ne peut pas être vide.';
                } elseif (mb_strlen($body) > self::NOTE_MAX) {
                    $error = sprintf('La note est limitée à %d caractères.', self::NOTE_MAX);
                }
                if ($error !== null) {
                    return ['form' => 'note:' . $idea['id'], 'error' => $error, 'old' => ['body' => $body], 'status' => 422];
                }
                $repo->saveNote((int) $idea['id'], $myId, $body);
                $done('note_saved', (int) $idea['id']);

            case 'delete_note':
                $note = $repo->note($a->id, (int) ($_POST['note_id'] ?? 0)) ?? abort(404, 'Note introuvable.');
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
        if ($repo->theme($this->a->id, $v['theme_id']) === null) {
            return 'Choisissez un thème.';
        }
        $len = mb_strlen($v['title']);
        if ($len < self::TITLE_MIN || $len > self::TITLE_MAX) {
            return sprintf('Le titre doit faire entre %d et %d caractères.', self::TITLE_MIN, self::TITLE_MAX);
        }
        if (mb_strlen($v['description']) > self::DESC_MAX) {
            return sprintf('La description est limitée à %d caractères.', self::DESC_MAX);
        }
        return null;
    }

    private function show(Repo $repo, ?array $me, bool $admin, ?array $ctx): never
    {
        $in = $this->in;
        $a = $this->a;
        $themes = $repo->themes($a->id);

        $active = null;
        $wanted = (int) ($_GET['theme'] ?? 0);
        foreach ($themes as $t) {
            if ((int) $t['id'] === $wanted) {
                $active = $t;
            }
        }

        $ideas = $repo->ideas($a->id, $active !== null ? (int) $active['id'] : null, $me !== null ? (int) $me['id'] : 0);
        $counts = $repo->ideaCountsByTheme($a->id);

        $themeNumber = [];
        foreach ($themes as $i => $t) {
            $themeNumber[(int) $t['id']] = $i + 1;
        }

        $flashKey = is_string($_GET['m'] ?? null) ? $_GET['m'] : '';
        $form = $ctx['form'] ?? null;

        render('consultation', [
            'in'          => $in,
            'activity'    => $a,
            'pageTitle'   => $a->title . ' — ' . $in->name(),
            'heading'     => $in->name(),
            'headerLink'  => [url($in->slug . '/'), 'Accueil'],
            'me'          => $me,
            'admin'       => $admin,
            'open'        => $a->isOpen(),
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
