<?php
declare(strict_types=1);

namespace Appc;

/**
 * Espace administrateur d'un établissement (généralement le représentant des parents) : activités,
 * annonces, codes d'accès, réglages. La gestion détaillée d'une activité est dans ActivityAdmin.
 */
final class AdminPanel
{
    public const POST_TITLE_MAX = 120;
    public const POST_BODY_MAX = 2000;
    public const POST_LOCATION_MAX = 80;

    private const FLASH = [
        'settings_saved'   => 'Réglages enregistrés.',
        'activity_deleted' => 'Activité supprimée.',
        'post_saved'       => 'Annonce publiée.',
        'post_deleted'     => 'Annonce supprimée.',
        'code_created'     => 'Code créé. Transmettez-le aux parents concernés.',
        'code_revoked'     => 'Code révoqué : les personnes entrées avec ce code n\'ont plus accès.',
        'password_changed' => 'Mot de passe modifié.',
    ];

    public function __construct(private Instance $in)
    {
    }

    public function handle(): never
    {
        $in = $this->in;
        if (!Access::isAdmin($in)) {
            $this->login();
        }
        $repo = new Repo($in->db);
        $error = null;
        if (Http::method() === 'POST') {
            Csrf::check();
            $error = $this->post($repo);
        }
        $this->show($repo, $error);
    }

    private function login(): never
    {
        $in = $this->in;
        $error = null;
        $status = 200;
        if (Http::method() === 'POST') {
            Csrf::check();
            $limiter = new Limiter($in->db, 'admin');
            if ($limiter->blocked()) {
                $error = Access::MSG_BLOCKED;
                $status = 429;
            } elseif (($_POST['action'] ?? '') === 'login'
                && Access::checkAdminPassword($in, (string) ($_POST['password'] ?? ''))) {
                $limiter->clear();
                Access::startAdmin($in);
                Http::redirect(url($in->slug . '/admin'));
            } else {
                $limiter->fail();
                $error = 'Mot de passe incorrect.';
                $status = 401;
            }
        }
        render('admin_login', [
            'in'        => $in,
            'pageTitle' => 'Administration — ' . $in->name(),
            'heading'   => $in->name(),
            'error'     => $error,
        ], $status);
    }

    /** Traite une action ; redirige en cas de succès, retourne un message d'erreur sinon. */
    private function post(Repo $repo): string
    {
        $in = $this->in;
        $done = static fn(string $m): never => Http::redirect(url($in->slug . '/admin') . '?m=' . $m);
        $text = static fn(string $k): string => clean_text($_POST[$k] ?? '');

        switch ((string) ($_POST['action'] ?? '')) {
            case 'logout':
                Access::endAdmin($in);
                Http::redirect(url($in->slug . '/acces'));

            case 'save_settings':
                $name = $text('name');
                $intro = $text('intro');
                if ($name === '' || mb_strlen($name) > 80) {
                    return 'Le nom doit faire entre 1 et 80 caractères.';
                }
                if (mb_strlen($intro) > 600) {
                    return 'Le message d\'accueil est limité à 600 caractères.';
                }
                $in->set('name', $name);
                $in->set('intro', $intro);
                $done('settings_saved');

            case 'create_activity':
                $kind = (string) ($_POST['kind'] ?? '');
                $title = $text('title');
                if (!Activity::validKind($kind)) {
                    return 'Choisissez un type d\'activité.';
                }
                if ($title === '' || mb_strlen($title) > Activity::TITLE_MAX) {
                    return sprintf('Le titre doit faire entre 1 et %d caractères.', Activity::TITLE_MAX);
                }
                $id = $repo->createActivity($kind, $title);
                Http::redirect(url($in->slug . '/admin/activite/' . $id) . '?m=created');

            case 'save_post':
                $id = (int) ($_POST['post_id'] ?? 0);
                if ($id > 0 && $repo->post($id) === null) {
                    return 'Annonce introuvable.';
                }
                $rawEvent = trim((string) ($_POST['event_at'] ?? ''));
                $p = [
                    'title'    => $text('title'),
                    'body'     => $text('body'),
                    'event_at' => parse_datetime($rawEvent),
                    'location' => $text('location'),
                    'pinned'   => ($_POST['pinned'] ?? '') === '1',
                ];
                if ($p['title'] === '' || mb_strlen($p['title']) > self::POST_TITLE_MAX) {
                    return sprintf('Le titre de l\'annonce doit faire entre 1 et %d caractères.', self::POST_TITLE_MAX);
                }
                if (mb_strlen($p['body']) > self::POST_BODY_MAX) {
                    return sprintf('Le texte de l\'annonce est limité à %d caractères.', self::POST_BODY_MAX);
                }
                if (mb_strlen($p['location']) > self::POST_LOCATION_MAX) {
                    return sprintf('Le lieu est limité à %d caractères.', self::POST_LOCATION_MAX);
                }
                if ($rawEvent !== '' && $p['event_at'] === null) {
                    return 'Date de l\'événement invalide.';
                }
                $repo->savePost($id > 0 ? $id : null, $p);
                $done('post_saved');

            case 'delete_post':
                $repo->deletePost((int) ($_POST['post_id'] ?? 0));
                $done('post_deleted');

            case 'create_code':
                $label = $text('label');
                $rawMax = trim((string) ($_POST['max_uses'] ?? ''));
                $rawExp = trim((string) ($_POST['expires_at'] ?? ''));
                $exp = parse_datetime($rawExp);
                if (mb_strlen($label) > 60) {
                    return 'L\'étiquette est limitée à 60 caractères.';
                }
                $maxUses = null;
                if ($rawMax !== '') {
                    $maxUses = (int) $rawMax;
                    if ($maxUses < 1 || $maxUses > 10000) {
                        return 'Le nombre d\'utilisations doit être compris entre 1 et 10 000 (vide = illimité).';
                    }
                }
                if ($rawExp !== '' && ($exp === null || $exp <= time())) {
                    return 'La date d\'expiration doit être valide et dans le futur.';
                }
                Access::createCode($in, $label, $maxUses, $exp);
                $done('code_created');

            case 'revoke_code':
                $repo->revokeCode((int) ($_POST['code_id'] ?? 0));
                $done('code_revoked');

            case 'change_password':
                $new = (string) ($_POST['new_password'] ?? '');
                if (!Access::checkAdminPassword($in, (string) ($_POST['current_password'] ?? ''))) {
                    return 'Le mot de passe actuel est incorrect.';
                }
                if (strlen($new) < 10 || strlen($new) > 200) {
                    return 'Le nouveau mot de passe doit faire au moins 10 caractères.';
                }
                if (!hash_equals($new, (string) ($_POST['confirm_password'] ?? ''))) {
                    return 'La confirmation ne correspond pas au nouveau mot de passe.';
                }
                $in->set('admin_hash', password_hash($new, PASSWORD_DEFAULT));
                $in->set('must_change_pw', '0');
                Access::endAllAdminSessions($in);
                Access::startAdmin($in);
                $done('password_changed');
        }
        abort(400, 'Action inconnue.');
    }

    private function show(Repo $repo, ?string $error): never
    {
        $in = $this->in;
        $flashKey = is_string($_GET['m'] ?? null) ? $_GET['m'] : '';
        $editPost = isset($_GET['post']) ? $repo->post((int) $_GET['post']) : null;
        render('admin', [
            'in'         => $in,
            'pageTitle'  => 'Administration — ' . $in->name(),
            'heading'    => $in->name(),
            'headerLink' => [url($in->slug . '/'), 'Retour au portail'],
            'flash'      => self::FLASH[$flashKey] ?? null,
            'error'      => $error,
            'activities' => $repo->activities(),
            'counts'     => $repo->activityCounts(),
            'posts'      => $repo->posts(),
            'editPost'   => $editPost,
            'codes'      => $repo->codes(),
            'stats'      => $repo->stats(),
            'mustChange' => $in->setting('must_change_pw', '0') === '1',
        ], $error === null ? 200 : 422);
    }
}
