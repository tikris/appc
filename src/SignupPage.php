<?php
declare(strict_types=1);

namespace Appc;

/**
 * Page d'inscriptions bénévoles : le participant choisit un ou plusieurs créneaux et indique un prénom
 * (ou un pseudo). Les prénoms ne sont visibles des autres participants que si l'administrateur l'a choisi.
 */
final class SignupPage
{
    private const FLASH = [
        'joined' => 'Inscription enregistrée. Merci !',
        'left'   => 'Inscription annulée.',
    ];

    public function __construct(private Instance $in, private Activity $a)
    {
    }

    /** Le jeton CSRF des POST a déjà été vérifié par Portal. */
    public function handle(?array $me, bool $admin): never
    {
        $a = $this->a;
        $repo = new SignupRepo($this->in->db);
        $owner = $me !== null ? Access::pseudonym('signup') : null;
        [$error, $status, $name] = Http::method() === 'POST' ? $this->post($repo, $me, $owner) : [null, 200, null];

        render('signup', [
            'in'         => $this->in,
            'activity'   => $a,
            'pageTitle'  => $a->title . ' — ' . $this->in->name(),
            'heading'    => $this->in->name(),
            'headerLink' => [url($this->in->slug . '/'), 'Accueil'],
            'me'         => $me,
            'admin'      => $admin,
            'slots'      => $repo->slots($a->id),
            'signups'    => ($admin || $a->showNames) ? $repo->signupsBySlot($a->id) : [],
            'mySlots'    => $owner !== null ? $repo->mySlotIds($a->id, $owner) : [],
            'name'       => $name ?? ($owner !== null ? $repo->lastName($owner) : ''),
            'flash'      => self::FLASH[is_string($_GET['m'] ?? null) ? $_GET['m'] : ''] ?? null,
            'error'      => $error,
        ], $status);
    }

    /**
     * Traite une inscription ou une annulation et redirige ; sinon retourne [message, statut HTTP, prénom saisi].
     * @return array{0:string,1:int,2:string}
     */
    private function post(SignupRepo $repo, ?array $me, ?string $owner): array
    {
        $a = $this->a;
        $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['join', 'leave'], true)) {
            abort(400, 'Action inconnue.');
        }
        if ($me === null || $owner === null) {
            abort(403, 'Seul un participant disposant d\'un code peut s\'inscrire.');
        }
        if (!$a->isOpen()) {
            abort(403, $a->notOpenMessage());
        }
        $slot = $repo->slot($a->id, (int) ($_POST['slot_id'] ?? 0)) ?? abort(404, 'Créneau introuvable.');
        $done = fn(string $m): never => Http::redirect(url($this->in->slug . '/activite/' . $a->id) . '?m=' . $m . '#slot-' . $slot['id']);

        if ($action === 'leave') {
            $repo->leave((int) $slot['id'], $owner);
            $done('left');
        }
        $name = clean_text($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > SignupRepo::NAME_MAX) {
            return [sprintf('Indiquez un prénom ou un pseudo (%d caractères au plus).', SignupRepo::NAME_MAX), 422, $name];
        }
        $error = $repo->join($slot, $owner, $name);
        if ($error !== null) {
            return [$error, 409, $name];
        }
        $done('joined');
    }
}
