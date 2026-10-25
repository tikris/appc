<?php
declare(strict_types=1);

namespace Appc;

/**
 * Gestion d'une activité par l'administrateur : réglages, publication, clôture, suppression, export,
 * et contenu propre à chaque type (thèmes d'une consultation, réponses d'un sondage, créneaux et inscrits).
 */
final class ActivityAdmin
{
    private const FLASH = [
        'created'        => 'Activité créée en brouillon : complétez-la, puis publiez-la.',
        'saved'          => 'Réglages enregistrés.',
        'published'      => 'Activité publiée : elle est visible des participants.',
        'unpublished'    => 'Activité repassée en brouillon : elle n\'est plus visible des participants.',
        'closed'         => 'Activité close. Les résultats restent visibles.',
        'reopened'       => 'Activité de nouveau ouverte.',
        'theme_added'    => 'Thème ajouté.',
        'theme_updated'  => 'Thème modifié.',
        'theme_deleted'  => 'Thème supprimé.',
        'option_added'   => 'Réponse ajoutée.',
        'option_updated' => 'Réponse modifiée.',
        'option_deleted' => 'Réponse supprimée.',
        'slot_added'     => 'Créneau ajouté.',
        'slot_updated'   => 'Créneau modifié.',
        'slot_deleted'   => 'Créneau supprimé.',
        'signup_added'   => 'Inscription ajoutée.',
        'signup_removed' => 'Inscription retirée.',
    ];

    private const THEMES_MAX = 12;
    private const OPTIONS_MAX = 20;
    private const SLOTS_MAX = 50;

    private Repo $repo;

    public function __construct(private Instance $in)
    {
        $this->repo = new Repo($in->db);
    }

    public function handle(int $id): never
    {
        $in = $this->in;
        if (!Access::isAdmin($in)) {
            Http::redirect(url($in->slug . '/admin'));
        }
        $a = $this->repo->activity($id) ?? abort(404, 'Activité introuvable.');
        $error = null;
        if (Http::method() === 'POST') {
            Csrf::check();
            $error = $this->post($a);
        }
        $this->show($a, $error);
    }

    /** Traite une action ; redirige en cas de succès, retourne un message d'erreur sinon. */
    private function post(Activity $a): string
    {
        $repo = $this->repo;
        $self = url($this->in->slug . '/admin/activite/' . $a->id);
        $done = static fn(string $m): never => Http::redirect($self . '?m=' . $m);
        $text = static fn(string $k): string => clean_text($_POST[$k] ?? '');
        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'save_activity':
                $fields = $this->settingsInput($a);
                if (is_string($fields)) {
                    return $fields;
                }
                $repo->updateActivity($a->id, $fields);
                $done('saved');

            case 'publish':
                $missing = match ($a->kind) {
                    'consultation' => $repo->themes($a->id) === [] ? 'Ajoutez au moins un thème avant de publier.' : null,
                    'poll'         => count((new PollRepo($this->in->db))->options($a->id)) < 2 ? 'Ajoutez au moins deux réponses avant de publier.' : null,
                    'signup'       => (new SignupRepo($this->in->db))->slots($a->id) === [] ? 'Ajoutez au moins un créneau avant de publier.' : null,
                };
                if ($missing !== null) {
                    return $missing;
                }
                $repo->updateActivity($a->id, ['published_at' => time()]);
                $done('published');

            case 'unpublish':
                $repo->updateActivity($a->id, ['published_at' => null]);
                $done('unpublished');

            case 'close_now':
                $repo->updateActivity($a->id, ['closed_at' => time()]);
                $done('closed');

            case 'reopen':
                // Une échéance dépassée rouvrirait puis refermerait aussitôt : elle est effacée.
                $expired = $a->closesAt !== null && $a->closesAt <= time();
                $repo->updateActivity($a->id, ['closed_at' => null] + ($expired ? ['closes_at' => null] : []));
                $done('reopened');

            case 'delete_activity':
                $repo->deleteActivity($a->id);
                Http::redirect(url($this->in->slug . '/admin') . '?m=activity_deleted');
        }

        return match ($a->kind) {
            'consultation' => $this->postConsultation($a, $action, $text, $done),
            'poll'         => $this->postPoll($a, $action, $text, $done),
            'signup'       => $this->postSignup($a, $action, $text, $done),
        };
    }

    /**
     * Lit et valide le formulaire de réglages.
     * @return array<string,int|string|null>|string colonnes à enregistrer, ou message d'erreur
     */
    private function settingsInput(Activity $a): array|string
    {
        $title = clean_text($_POST['title'] ?? '');
        $description = clean_text($_POST['description'] ?? '');
        $rawStart = trim((string) ($_POST['opens_at'] ?? ''));
        $start = parse_datetime($rawStart);
        $rawEnd = trim((string) ($_POST['closes_at'] ?? ''));
        $end = parse_datetime($rawEnd);
        if ($title === '' || mb_strlen($title) > Activity::TITLE_MAX) {
            return sprintf('Le titre doit faire entre 1 et %d caractères.', Activity::TITLE_MAX);
        }
        if (mb_strlen($description) > Activity::DESC_MAX) {
            return sprintf('La description est limitée à %d caractères.', Activity::DESC_MAX);
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
        $fields = ['title' => $title, 'description' => $description, 'opens_at' => $start, 'closes_at' => $end];

        switch ($a->kind) {
            case 'consultation':
                $max = (int) ($_POST['max_ideas'] ?? 0);
                if ($max < 1 || $max > 50) {
                    return 'Le nombre maximal de propositions doit être compris entre 1 et 50.';
                }
                $fields['max_ideas'] = $max;
                break;
            case 'poll':
                $results = (string) ($_POST['results'] ?? '');
                if (!isset(Activity::RESULTS[$results])) {
                    return 'Choisissez quand les résultats sont visibles.';
                }
                $fields['results'] = $results;
                $multiple = ($_POST['multiple'] ?? '') === '1';
                if ($multiple !== $a->multiple && (new PollRepo($this->in->db))->respondents($a->id) > 0) {
                    return 'Des participants ont déjà répondu : le nombre de réponses possibles ne peut plus changer.';
                }
                $fields['multiple'] = $multiple ? 1 : 0;
                break;
            case 'signup':
                $fields['show_names'] = ($_POST['show_names'] ?? '') === '1' ? 1 : 0;
                break;
        }
        return $fields;
    }

    // ── Consultation : thèmes ───────────────────────────────────────────────

    private function postConsultation(Activity $a, string $action, \Closure $text, \Closure $done): string
    {
        $repo = $this->repo;
        $name = $text('name');
        $color = (int) ($_POST['color'] ?? 0);
        switch ($action) {
            case 'add_theme':
                if ($name === '' || mb_strlen($name) > 60 || $color < 1 || $color > 8) {
                    return 'Nom (60 caractères maximum) et couleur sont obligatoires.';
                }
                if (count($repo->themes($a->id)) >= self::THEMES_MAX) {
                    return sprintf('%d thèmes maximum.', self::THEMES_MAX);
                }
                $repo->addTheme($a->id, $name, $color);
                $done('theme_added');

            case 'update_theme':
                $theme = $repo->theme($a->id, (int) ($_POST['theme_id'] ?? 0));
                $pos = (int) ($_POST['position'] ?? 0);
                if ($theme === null) {
                    return 'Thème introuvable.';
                }
                if ($name === '' || mb_strlen($name) > 60 || $color < 1 || $color > 8 || $pos < 1 || $pos > 99) {
                    return 'Nom (60 caractères maximum), couleur et position (1 à 99) sont obligatoires.';
                }
                $repo->updateTheme((int) $theme['id'], $name, $color, $pos);
                $done('theme_updated');

            case 'delete_theme':
                $theme = $repo->theme($a->id, (int) ($_POST['theme_id'] ?? 0));
                if ($theme === null) {
                    return 'Thème introuvable.';
                }
                if (($repo->ideaCountsByTheme($a->id)[(int) $theme['id']] ?? 0) > 0) {
                    return 'Ce thème contient des propositions : supprimez-les d\'abord, ou renommez le thème.';
                }
                if (count($repo->themes($a->id)) <= 1) {
                    return 'Conservez au moins un thème.';
                }
                $repo->deleteTheme((int) $theme['id']);
                $done('theme_deleted');
        }
        abort(400, 'Action inconnue.');
    }

    // ── Sondage : réponses proposées ────────────────────────────────────────

    private function postPoll(Activity $a, string $action, \Closure $text, \Closure $done): string
    {
        $polls = new PollRepo($this->in->db);
        if (in_array($action, ['add_option', 'update_option', 'delete_option'], true) && $polls->respondents($a->id) > 0) {
            return 'Des participants ont déjà répondu : les réponses proposées ne peuvent plus être modifiées.';
        }
        $label = $text('label');
        $labelError = ($label === '' || mb_strlen($label) > 100) ? 'Le libellé doit faire entre 1 et 100 caractères.' : null;
        switch ($action) {
            case 'add_option':
                if ($labelError !== null) {
                    return $labelError;
                }
                if (count($polls->options($a->id)) >= self::OPTIONS_MAX) {
                    return sprintf('%d réponses maximum.', self::OPTIONS_MAX);
                }
                $polls->addOption($a->id, $label);
                $done('option_added');

            case 'update_option':
                $option = $polls->option($a->id, (int) ($_POST['option_id'] ?? 0)) ?? abort(404, 'Réponse introuvable.');
                $pos = (int) ($_POST['position'] ?? 0);
                if ($labelError !== null) {
                    return $labelError;
                }
                if ($pos < 1 || $pos > 99) {
                    return 'La position doit être comprise entre 1 et 99.';
                }
                $polls->updateOption((int) $option['id'], $label, $pos);
                $done('option_updated');

            case 'delete_option':
                $option = $polls->option($a->id, (int) ($_POST['option_id'] ?? 0)) ?? abort(404, 'Réponse introuvable.');
                $polls->deleteOption((int) $option['id']);
                $done('option_deleted');
        }
        abort(400, 'Action inconnue.');
    }

    // ── Inscriptions : créneaux et inscrits ─────────────────────────────────

    private function postSignup(Activity $a, string $action, \Closure $text, \Closure $done): string
    {
        $slots = new SignupRepo($this->in->db);
        switch ($action) {
            case 'add_slot':
            case 'update_slot':
                $slot = $action === 'update_slot'
                    ? ($slots->slot($a->id, (int) ($_POST['slot_id'] ?? 0)) ?? abort(404, 'Créneau introuvable.'))
                    : null;
                $label = $text('label');
                $capacity = (int) ($_POST['capacity'] ?? 0);
                $rawStart = trim((string) ($_POST['starts_at'] ?? ''));
                $start = parse_datetime($rawStart);
                if ($label === '' || mb_strlen($label) > 100) {
                    return 'Le libellé du créneau doit faire entre 1 et 100 caractères.';
                }
                if ($capacity < 1 || $capacity > 500) {
                    return 'Le nombre de places doit être compris entre 1 et 500.';
                }
                if ($rawStart !== '' && $start === null) {
                    return 'Date du créneau invalide.';
                }
                if ($slot === null) {
                    if (count($slots->slots($a->id)) >= self::SLOTS_MAX) {
                        return sprintf('%d créneaux maximum.', self::SLOTS_MAX);
                    }
                    $slots->addSlot($a->id, $label, $start, $capacity);
                    $done('slot_added');
                }
                $pos = (int) ($_POST['position'] ?? 0);
                if ($pos < 1 || $pos > 99) {
                    return 'La position doit être comprise entre 1 et 99.';
                }
                $taken = count($slots->signupsBySlot($a->id)[(int) $slot['id']] ?? []);
                if ($capacity < $taken) {
                    return sprintf('Ce créneau compte déjà %d inscrit(s) : retirez-en avant de réduire le nombre de places.', $taken);
                }
                $slots->updateSlot((int) $slot['id'], $label, $start, $capacity, $pos);
                $done('slot_updated');

            case 'delete_slot':
                $slot = $slots->slot($a->id, (int) ($_POST['slot_id'] ?? 0)) ?? abort(404, 'Créneau introuvable.');
                $slots->deleteSlot((int) $slot['id']);
                $done('slot_deleted');

            case 'add_signup':
                // Inscription reçue hors du portail (papier, téléphone) : identifiant aléatoire, sans lien avec un navigateur.
                $slot = $slots->slot($a->id, (int) ($_POST['slot_id'] ?? 0)) ?? abort(404, 'Créneau introuvable.');
                $name = $text('name');
                if ($name === '' || mb_strlen($name) > SignupRepo::NAME_MAX) {
                    return sprintf('Indiquez un prénom ou un pseudo (%d caractères au plus).', SignupRepo::NAME_MAX);
                }
                $error = $slots->join($slot, 'admin|' . bin2hex(random_bytes(16)), $name);
                if ($error !== null) {
                    return $error;
                }
                $done('signup_added');

            case 'remove_signup':
                $signup = $slots->signup($a->id, (int) ($_POST['signup_id'] ?? 0)) ?? abort(404, 'Inscription introuvable.');
                $slots->deleteSignup((int) $signup['id']);
                $done('signup_removed');
        }
        abort(400, 'Action inconnue.');
    }

    private function show(Activity $a, ?string $error): never
    {
        $in = $this->in;
        $db = $in->db;
        $flashKey = is_string($_GET['m'] ?? null) ? $_GET['m'] : '';
        $vars = [
            'in'         => $in,
            'activity'   => $a,
            'pageTitle'  => $a->title . ' — Administration',
            'heading'    => $in->name(),
            'headerLink' => [url($in->slug . '/admin'), 'Retour à l\'administration'],
            'flash'      => self::FLASH[$flashKey] ?? null,
            'error'      => $error,
            'counts'     => $this->repo->activityCounts()[$a->id],
        ];
        $vars += match ($a->kind) {
            'consultation' => ['themes' => $this->repo->themes($a->id), 'themeCount' => $this->repo->ideaCountsByTheme($a->id)],
            'poll'         => ['options' => (new PollRepo($db))->options($a->id), 'respondents' => (new PollRepo($db))->respondents($a->id)],
            'signup'       => ['slots' => (new SignupRepo($db))->slots($a->id), 'signups' => (new SignupRepo($db))->signupsBySlot($a->id)],
        };
        render('admin_activity', $vars, $error === null ? 200 : 422);
    }

    public function export(int $id): never
    {
        $in = $this->in;
        if (!Access::isAdmin($in)) {
            abort(403, 'Accès réservé à l\'administrateur.');
        }
        $a = $this->repo->activity($id) ?? abort(404, 'Activité introuvable.');
        $file = 'appc-' . $in->slug . '-' . $a->id . '-' . date('Ymd') . '.csv';
        match ($a->kind) {
            'consultation' => send_csv($file, ['id', 'theme', 'titre', 'description', 'votes', 'creee_le', 'modifiee_le', 'notes_de_contexte'], $this->repo->exportIdeas($a->id)),
            'poll'         => send_csv($file, ['reponse', 'nombre', 'part_des_participants'], (new PollRepo($in->db))->exportRows($a->id)),
            'signup'       => send_csv($file, ['creneau', 'date', 'prenom_ou_pseudo', 'inscrit_le'], (new SignupRepo($in->db))->exportRows($a->id)),
        };
    }
}
