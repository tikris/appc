<?php
declare(strict_types=1);

namespace Appc;

/** Une instance = un établissement = un fichier SQLite = une URL /<slug>/. */
final class Instance
{
    public const RESERVED = ['_super', 'assets', 'public', 'admin', 'static', 'api', 'acces', 'robots.txt', 'favicon.ico'];

    /** Thèmes proposés à la création (modifiables ensuite par l'administrateur). */
    private const DEFAULT_THEMES = [
        ['Rythmes scolaires', 1],
        ['Organisation périscolaire', 2],
        ['Encadrement des enfants', 3],
        ['Protection des enfants', 4],
    ];

    /** @var array<string,string> */
    private array $settings = [];

    private function __construct(public readonly string $slug, public readonly \PDO $db)
    {
        foreach ($db->query('SELECT key, value FROM settings') as $row) {
            $this->settings[$row['key']] = $row['value'];
        }
    }

    public static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $slug)
            && !in_array($slug, self::RESERVED, true);
    }

    private static function path(string $slug): string
    {
        return Config::dataDir() . '/' . $slug . '.sqlite';
    }

    public static function load(string $slug): ?self
    {
        if (!self::validSlug($slug) || !is_file(self::path($slug))) {
            return null;
        }
        return new self($slug, Db::open(self::path($slug)));
    }

    /** @return list<string> */
    public static function allSlugs(): array
    {
        $slugs = [];
        foreach (glob(Config::dataDir() . '/*.sqlite') ?: [] as $file) {
            $slug = basename($file, '.sqlite');
            if (self::validSlug($slug)) {
                $slugs[] = $slug;
            }
        }
        sort($slugs);
        return $slugs;
    }

    public static function create(string $slug, string $name, string $adminPassword): self
    {
        if (!self::validSlug($slug)) {
            throw new \InvalidArgumentException('Identifiant invalide : 3 à 40 caractères, minuscules, chiffres et tirets (ex. ecole-etienne-marcel).');
        }
        $name = clean_text($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Le nom doit faire entre 1 et 80 caractères.');
        }
        if (is_file(self::path($slug))) {
            throw new \InvalidArgumentException('Cet identifiant existe déjà.');
        }
        $db = Db::open(self::path($slug));
        @chmod(self::path($slug), 0660);

        $db->beginTransaction();
        $set = $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
        foreach ([
            'name'           => $name,
            'admin_hash'     => password_hash($adminPassword, PASSWORD_DEFAULT),
            'must_change_pw' => '1',
            'created_at'     => (string) time(),
        ] as $k => $v) {
            $set->execute([$k, $v]);
        }
        $theme = $db->prepare('INSERT INTO themes (name, color, position) VALUES (?, ?, ?)');
        foreach (self::DEFAULT_THEMES as $i => [$themeName, $color]) {
            $theme->execute([$themeName, $color, $i + 1]);
        }
        $db->commit();

        return new self($slug, $db);
    }

    // ── Réglages ────────────────────────────────────────────────────────────

    public function setting(string $key, ?string $default = null): ?string
    {
        return $this->settings[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
        $this->settings[$key] = $value;
    }

    public function name(): string
    {
        return $this->setting('name', $this->slug) ?? $this->slug;
    }

    public function intro(): string
    {
        return $this->setting('intro', '') ?? '';
    }

    /* Limites reprises du portail d'origine (/cc/) : titre 3 à 120, description facultative jusqu'à 400, note 600. */
    public function titleMin(): int
    {
        return 3;
    }

    public function titleMax(): int
    {
        return 120;
    }

    public function descMax(): int
    {
        return 400;
    }

    public function noteMax(): int
    {
        return 600;
    }

    /** Nombre maximal de propositions par participant (anti-spam). */
    public function maxIdeas(): int
    {
        return max(1, min(50, (int) $this->setting('max_ideas', '5')));
    }

    /** Date d'ouverture des propositions et des votes (null = ouvert dès la création). */
    public function opensAt(): ?int
    {
        $v = $this->setting('opens_at', '');
        return ($v !== null && $v !== '') ? (int) $v : null;
    }

    /** Date de fin prévue des votes (null = pas d'échéance). */
    public function closesAt(): ?int
    {
        $v = $this->setting('closes_at', '');
        return ($v !== null && $v !== '') ? (int) $v : null;
    }

    /** Date de clôture manuelle (null = non clôturé à la main). */
    public function closedAt(): ?int
    {
        $v = $this->setting('closed_at', '');
        return ($v !== null && $v !== '') ? (int) $v : null;
    }

    /** « before » (pas encore ouvert), « open » ou « closed ». La clôture manuelle l'emporte sur tout. */
    public function phase(): string
    {
        if ($this->closedAt() !== null) {
            return 'closed';
        }
        $now = time();
        $start = $this->opensAt();
        if ($start !== null && $now < $start) {
            return 'before';
        }
        $end = $this->closesAt();
        return ($end !== null && $now >= $end) ? 'closed' : 'open';
    }

    public function isOpen(): bool
    {
        return $this->phase() === 'open';
    }

    /** Message affiché quand une action exige un scrutin ouvert. */
    public function notOpenMessage(): string
    {
        return $this->phase() === 'before'
            ? 'La période de vote n\'a pas encore commencé.'
            : 'La période de vote est terminée.';
    }

    /** Jours entiers restant avant l'échéance (comme le « J-N » du portail d'origine) ; null sans échéance. */
    public function daysLeft(): ?int
    {
        $end = $this->closesAt();
        return $end === null ? null : max(0, intdiv($end - time(), 86400));
    }

    /** Date à afficher pour « clôturée le … ». */
    public function closedOn(): ?int
    {
        return $this->closedAt() ?? $this->closesAt();
    }
}
