<?php
declare(strict_types=1);

namespace Appc;

/** Page d'un sondage : une réponse par participant, modifiable tant que le sondage est ouvert. */
final class PollPage
{
    private const FLASH = ['answered' => 'Votre réponse a été enregistrée.'];

    public function __construct(private Instance $in, private Activity $a)
    {
    }

    /** Le jeton CSRF des POST a déjà été vérifié par Portal. */
    public function handle(?array $me, bool $admin): never
    {
        $a = $this->a;
        $polls = new PollRepo($this->in->db);
        $error = Http::method() === 'POST' ? $this->post($polls, $me) : null;

        $mine = $me !== null ? $polls->answersOf($a->id, (int) $me['id']) : [];
        $showResults = $admin
            || $a->results === 'live'
            || $a->phase() === 'closed'
            || ($a->results === 'voted' && $mine !== []);

        render('poll', [
            'in'          => $this->in,
            'activity'    => $a,
            'pageTitle'   => $a->title . ' — ' . $this->in->name(),
            'heading'     => $this->in->name(),
            'headerLink'  => [url($this->in->slug . '/'), 'Accueil'],
            'me'          => $me,
            'admin'       => $admin,
            'options'     => $polls->options($a->id),
            'respondents' => $polls->respondents($a->id),
            'mine'        => $error !== null ? $this->chosen() : $mine,
            'showResults' => $showResults,
            'flash'       => self::FLASH[is_string($_GET['m'] ?? null) ? $_GET['m'] : ''] ?? null,
            'error'       => $error,
        ], $error === null ? 200 : 422);
    }

    /** Enregistre la réponse et redirige ; retourne un message d'erreur sinon. */
    private function post(PollRepo $polls, ?array $me): string
    {
        $a = $this->a;
        if (($_POST['action'] ?? '') !== 'answer') {
            abort(400, 'Action inconnue.');
        }
        if ($me === null) {
            abort(403, 'Seul un participant disposant d\'un code peut répondre.');
        }
        if (!$a->isOpen()) {
            abort(403, $a->notOpenMessage());
        }
        $chosen = $this->chosen();
        foreach ($chosen as $id) {
            if ($polls->option($a->id, $id) === null) {
                return 'Réponse inconnue : rechargez la page et réessayez.';
            }
        }
        if ($chosen === []) {
            return $a->multiple ? 'Cochez au moins une réponse.' : 'Choisissez une réponse.';
        }
        if (!$a->multiple && count($chosen) > 1) {
            return 'Une seule réponse est possible.';
        }
        $polls->answer($a->id, (int) $me['id'], $chosen);
        Http::redirect(url($this->in->slug . '/activite/' . $a->id) . '?m=answered');
    }

    /** @return list<int> options cochées dans le formulaire envoyé */
    private function chosen(): array
    {
        $raw = $_POST['options'] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        return array_values(array_unique(array_map('intval', array_filter($raw, 'is_string'))));
    }
}
