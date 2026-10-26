<?php
declare(strict_types=1);

namespace Appc;

/** Côté participants : saisie du code, tableau de bord, et aiguillage vers la page de chaque activité. */
final class Portal
{
    /** Nombre maximal de lignes de l'agenda du tableau de bord. */
    private const AGENDA_MAX = 8;

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

    // ── Tableau de bord ─────────────────────────────────────────────────────

    public function index(): never
    {
        $in = $this->in;
        [$me, $admin] = $this->viewer();
        if (Http::method() !== 'GET') {
            abort(400, 'Action inconnue.');
        }
        $repo = new Repo($in->db);
        $activities = ['open' => [], 'before' => [], 'closed' => []];
        foreach ($repo->activities(true) as $a) {
            $activities[$a->phase()][] = $a;
        }

        render('home', [
            'in'         => $in,
            'pageTitle'  => $in->name(),
            'heading'    => $in->name(),
            'headerLink' => [url($in->slug . '/admin'), $admin ? 'Administration' : 'Admin'],
            'me'         => $me,
            'admin'      => $admin,
            'activities' => $activities,
            'counts'     => $repo->activityCounts(),
            'done'       => $me !== null ? $this->participation($activities['open'], $me) : [],
            'agenda'     => $this->agenda($repo, [...$activities['open'], ...$activities['before']]),
            'posts'      => $repo->posts(),
        ]);
    }

    /**
     * Événements datés à partir d'aujourd'hui et échéances des activités, du plus proche au plus lointain.
     * @param list<Activity> $activities activités publiées, ouvertes ou à venir
     * @return list<array{at:int,title:string,location:string,href:string}>
     */
    private function agenda(Repo $repo, array $activities): array
    {
        $slug = $this->in->slug;
        $items = [];
        foreach ($repo->upcomingEvents((int) strtotime('today')) as $p) {
            $items[] = ['at' => (int) $p['event_at'], 'title' => $p['title'], 'location' => $p['location'], 'href' => '#post-' . $p['id']];
        }
        foreach ($activities as $a) {
            $href = url($slug . '/activite/' . $a->id);
            if ($a->phase() === 'before') {
                $items[] = ['at' => (int) $a->opensAt, 'title' => 'Ouverture : ' . $a->title, 'location' => '', 'href' => $href];
            } elseif ($a->closesAt !== null) {
                $items[] = ['at' => $a->closesAt, 'title' => 'Clôture : ' . $a->title, 'location' => '', 'href' => $href];
            }
        }
        usort($items, static fn(array $x, array $y): int => $x['at'] <=> $y['at']);
        return array_slice($items, 0, self::AGENDA_MAX);
    }

    /**
     * Ce que le participant a déjà fait dans les activités ouvertes (badge sur le tableau de bord).
     * @param list<Activity> $open
     * @return array<int,string> activity_id => libellé
     */
    private function participation(array $open, array $me): array
    {
        $db = $this->in->db;
        $out = [];
        foreach ($open as $a) {
            $done = match ($a->kind) {
                'poll'   => (new PollRepo($db))->answersOf($a->id, (int) $me['id']) !== [] ? 'Vous avez répondu' : null,
                'signup' => (new SignupRepo($db))->mySlotIds($a->id, Access::pseudonym('signup')) !== [] ? 'Vous êtes inscrit(e)' : null,
                default  => null,
            };
            if ($done !== null) {
                $out[$a->id] = $done;
            }
        }
        return $out;
    }

    // ── Page d'une activité ─────────────────────────────────────────────────

    public function activity(int $id): never
    {
        [$me, $admin] = $this->viewer();
        $a = (new Repo($this->in->db))->activity($id);
        if ($a === null || (!$a->isPublished() && !$admin)) {
            abort(404, 'Cette activité n\'existe pas ou n\'est pas encore publiée.');
        }
        if (Http::method() === 'POST') {
            Csrf::check();
        }
        match ($a->kind) {
            'consultation' => (new ConsultationPage($this->in, $a))->handle($me, $admin),
            'poll'         => (new PollPage($this->in, $a))->handle($me, $admin),
            'signup'       => (new SignupPage($this->in, $a))->handle($me, $admin),
        };
    }

    /**
     * Participant courant et statut administrateur ; sans l'un ni l'autre, renvoie vers la saisie du code.
     * @return array{0:?array,1:bool}
     */
    private function viewer(): array
    {
        $me = Access::participant($this->in);
        $admin = Access::isAdmin($this->in);
        if ($me === null && !$admin) {
            Http::redirect(url($this->in->slug . '/acces'));
        }
        return [$me, $admin];
    }
}
