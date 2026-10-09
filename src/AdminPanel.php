<?php
declare(strict_types=1);

namespace Appc;

/** Espace administrateur d'un établissement (généralement le représentant des parents). */
final class AdminPanel
{
    private const FLASH = [
        'settings_saved'   => 'Réglages enregistrés.',
        'closed'           => 'Les votes sont clos. Les résultats restent visibles.',
        'reopened'         => 'Les votes sont de nouveau ouverts.',
        'code_created'     => 'Code créé. Transmettez-le aux parents concernés.',
        'code_revoked'     => 'Code révoqué : les personnes entrées avec ce code n\'ont plus accès.',
        'theme_added'      => 'Thème ajouté.',
        'theme_updated'    => 'Thème modifié.',
        'theme_deleted'    => 'Thème supprimé.',
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
                $max = (int) ($_POST['max_ideas'] ?? 0);
                $rawStart = trim((string) ($_POST['opens_at'] ?? ''));
                $start = parse_datetime($rawStart);
                $rawEnd = trim((string) ($_POST['closes_at'] ?? ''));
                $end = parse_datetime($rawEnd);
                if ($name === '' || mb_strlen($name) > 80) {
                    return 'Le nom doit faire entre 1 et 80 caractères.';
                }
                if (mb_strlen($intro) > 600) {
                    return 'Le message d\'accueil est limité à 600 caractères.';
                }
                if ($max < 1 || $max > 50) {
                    return 'Le nombre maximal de propositions doit être compris entre 1 et 50.';
                }
                if ($rawStart !== '' && $start === null) {
                    return 'Date de début invalide.';
                }
                if ($rawEnd !== '' && $end === null) {
                    return 'Date de fin invalide.';
                }
                if ($start !== null && $end !== null && $end <= $start) {
                    return 'La date de fin doit être postérieure à la date de début.';
                }
                $in->set('name', $name);
                $in->set('intro', $intro);
                $in->set('max_ideas', (string) $max);
                $in->set('opens_at', $start === null ? '' : (string) $start);
                $in->set('closes_at', $end === null ? '' : (string) $end);
                $done('settings_saved');

            case 'close_now':
                $in->set('closed_at', (string) time());
                $done('closed');

            case 'reopen':
                $in->set('closed_at', '');
                if ($in->closesAt() !== null && $in->closesAt() <= time()) {
                    $in->set('closes_at', ''); // l'échéance dépassée rouvrirait puis refermerait aussitôt
                }
                $done('reopened');

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

            case 'add_theme':
                $name = $text('name');
                $color = (int) ($_POST['color'] ?? 0);
                if ($name === '' || mb_strlen($name) > 60 || $color < 1 || $color > 8) {
                    return 'Nom (60 caractères maximum) et couleur sont obligatoires.';
                }
                if (count($repo->themes()) >= 12) {
                    return 'Douze thèmes maximum.';
                }
                $repo->addTheme($name, $color);
                $done('theme_added');

            case 'update_theme':
                $id = (int) ($_POST['theme_id'] ?? 0);
                $name = $text('name');
                $color = (int) ($_POST['color'] ?? 0);
                $pos = (int) ($_POST['position'] ?? 0);
                if ($repo->theme($id) === null) {
                    return 'Thème introuvable.';
                }
                if ($name === '' || mb_strlen($name) > 60 || $color < 1 || $color > 8 || $pos < 1 || $pos > 99) {
                    return 'Nom (60 caractères maximum), couleur et position (1 à 99) sont obligatoires.';
                }
                $repo->updateTheme($id, $name, $color, $pos);
                $done('theme_updated');

            case 'delete_theme':
                $id = (int) ($_POST['theme_id'] ?? 0);
                if ($repo->theme($id) === null) {
                    return 'Thème introuvable.';
                }
                if (($repo->ideaCountsByTheme()[$id] ?? 0) > 0) {
                    return 'Ce thème contient des propositions : supprimez-les d\'abord, ou renommez le thème.';
                }
                if (count($repo->themes()) <= 1) {
                    return 'Conservez au moins un thème.';
                }
                $repo->deleteTheme($id);
                $done('theme_deleted');

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
        render('admin', [
            'in'         => $in,
            'pageTitle'  => 'Administration — ' . $in->name(),
            'heading'    => $in->name(),
            'headerLink' => [url($in->slug . '/'), 'Retour au portail'],
            'flash'      => self::FLASH[$flashKey] ?? null,
            'error'      => $error,
            'open'       => $in->isOpen(),
            'phase'      => $in->phase(),
            'themes'     => $repo->themes(),
            'themeCount' => $repo->ideaCountsByTheme(),
            'codes'      => $repo->codes(),
            'stats'      => $repo->stats(),
            'mustChange' => $in->setting('must_change_pw', '0') === '1',
        ], $error === null ? 200 : 422);
    }

    public function export(): never
    {
        $in = $this->in;
        if (!Access::isAdmin($in)) {
            abort(403, 'Accès réservé à l\'administrateur.');
        }
        $csvSafe = static function (mixed $v): string {
            $s = (string) $v;
            // Neutralise l'injection de formules dans les tableurs.
            return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'" . $s : $s;
        };
        Http::securityHeaders();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="appc-' . $in->slug . '-' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM : Excel reconnaît l'UTF-8
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'theme', 'titre', 'description', 'votes', 'creee_le', 'modifiee_le', 'notes_de_contexte'], ';', '"', '', "\r\n");
        foreach ((new Repo($in->db))->exportRows() as $row) {
            fputcsv($out, array_map($csvSafe, $row), ';', '"', '', "\r\n");
        }
        fclose($out);
        exit;
    }
}
