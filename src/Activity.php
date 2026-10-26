<?php
declare(strict_types=1);

namespace Appc;

/**
 * Une activité d'un établissement : consultation (idées, votes, notes), sondage ou inscriptions bénévoles.
 * Chacune a sa propre fenêtre d'ouverture et n'est visible des participants qu'une fois publiée.
 */
final class Activity
{
    /** Libellés par type ; open, before et closed alimentent le bandeau d'état de la page. */
    public const KINDS = [
        'consultation' => [
            'label'  => 'Consultation',
            'icon'   => '💡',
            'hint'   => 'Les parents proposent des idées, votent et ajoutent des notes de contexte.',
            'open'   => 'Vote ouvert jusqu\'au %s',
            'before' => 'La période de vote n\'a pas encore commencé',
            'closed' => 'La période de vote est terminée',
        ],
        'poll' => [
            'label'  => 'Sondage',
            'icon'   => '📊',
            'hint'   => 'Une question à choix : date de réunion, oui/non, préférence…',
            'open'   => 'Sondage ouvert jusqu\'au %s',
            'before' => 'Le sondage n\'est pas encore ouvert',
            'closed' => 'Le sondage est terminé',
        ],
        'signup' => [
            'label'  => 'Inscriptions',
            'icon'   => '🙋',
            'hint'   => 'Des créneaux à pourvoir : stand de kermesse, accompagnement de sortie…',
            'open'   => 'Inscriptions ouvertes jusqu\'au %s',
            'before' => 'Les inscriptions ne sont pas encore ouvertes',
            'closed' => 'Les inscriptions sont closes',
        ],
    ];

    public const RESULTS = [
        'live'   => 'Visibles de tous pendant le sondage',
        'voted'  => 'Visibles après avoir répondu',
        'closed' => 'Visibles seulement après la clôture',
    ];

    public const TITLE_MAX = 100;
    public const DESC_MAX = 1000;

    private function __construct(
        public readonly int $id,
        public readonly string $kind,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $opensAt,
        public readonly ?int $closesAt,
        public readonly ?int $closedAt,
        public readonly ?int $publishedAt,
        public readonly int $maxIdeas,
        public readonly bool $multiple,
        public readonly string $results,
        public readonly bool $showNames,
        public readonly int $createdAt,
    ) {
    }

    public static function fromRow(array $r): self
    {
        $int = static fn(mixed $v): ?int => $v === null ? null : (int) $v;
        return new self(
            (int) $r['id'],
            (string) $r['kind'],
            (string) $r['title'],
            (string) $r['description'],
            $int($r['opens_at']),
            $int($r['closes_at']),
            $int($r['closed_at']),
            $int($r['published_at']),
            (int) $r['max_ideas'],
            (bool) $r['multiple'],
            (string) $r['results'],
            (bool) $r['show_names'],
            (int) $r['created_at'],
        );
    }

    public static function validKind(string $kind): bool
    {
        return isset(self::KINDS[$kind]);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind]['label'];
    }

    public function icon(): string
    {
        return self::KINDS[$this->kind]['icon'];
    }

    public function isPublished(): bool
    {
        return $this->publishedAt !== null;
    }

    /** « before » (pas encore ouvert), « open » ou « closed ». La clôture manuelle l'emporte sur tout. */
    public function phase(): string
    {
        if ($this->closedAt !== null) {
            return 'closed';
        }
        $now = time();
        if ($this->opensAt !== null && $now < $this->opensAt) {
            return 'before';
        }
        return ($this->closesAt !== null && $now >= $this->closesAt) ? 'closed' : 'open';
    }

    /** Participation possible : publiée et dans sa fenêtre d'ouverture (l'aperçu d'un brouillon reste en lecture seule). */
    public function isOpen(): bool
    {
        return $this->isPublished() && $this->phase() === 'open';
    }

    /** Message affiché quand une action exige une activité ouverte. */
    public function notOpenMessage(): string
    {
        if (!$this->isPublished()) {
            return 'Cette activité est un brouillon : la participation sera possible une fois publiée.';
        }
        return self::KINDS[$this->kind][$this->phase() === 'before' ? 'before' : 'closed'] . '.';
    }

    /** Texte d'un libellé de KINDS pour cette activité (open, before, closed). */
    public function text(string $key): string
    {
        return self::KINDS[$this->kind][$key];
    }

    /** Jours entiers restant avant l'échéance (« J-N ») ; null sans échéance. */
    public function daysLeft(): ?int
    {
        return $this->closesAt === null ? null : max(0, intdiv($this->closesAt - time(), 86400));
    }

    /** Date à afficher pour « terminée le … ». */
    public function closedOn(): ?int
    {
        return $this->closedAt ?? $this->closesAt;
    }

    /** État lisible pour les listes : brouillon, à venir, ouverte, terminée. */
    public function stateLabel(): string
    {
        if (!$this->isPublished()) {
            return 'brouillon';
        }
        return ['before' => 'à venir', 'open' => 'ouverte', 'closed' => 'terminée'][$this->phase()];
    }
}
