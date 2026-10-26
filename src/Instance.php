<?php
declare(strict_types=1);

namespace Appc;

/** Une instance = un établissement = un fichier SQLite = une URL /<slug>/. */
final class Instance
{
    public const RESERVED = ['_super', 'assets', 'public', 'admin', 'static', 'api', 'acces', 'robots.txt', 'favicon.ico'];

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

    /** Crée une instance remplie selon un modèle d'établissement (voir Templates). */
    public static function create(string $slug, string $name, string $adminPassword, string $template = Templates::DEFAULT): self
    {
        if (!Templates::valid($template)) {
            throw new \InvalidArgumentException('Modèle inconnu : ' . implode(', ', array_keys(Templates::ALL)) . '.');
        }
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
            'intro'          => Templates::ALL[$template]['intro'],
        ] as $k => $v) {
            $set->execute([$k, $v]);
        }
        Templates::apply($db, $template);
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

    /** Message d'accueil du tableau de bord. */
    public function intro(): string
    {
        return $this->setting('intro', '') ?? '';
    }
}
