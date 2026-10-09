<?php
declare(strict_types=1);

namespace Appc;

final class Db
{
    /** Ouvre (et crée si besoin) la base d'une instance, puis applique les migrations. */
    public static function open(string $path): \PDO
    {
        $pdo = self::connect($path);
        self::migrate($pdo);
        return $pdo;
    }

    /** Base technique commune (limitation des essais de la page super-admin). */
    public static function system(): \PDO
    {
        return self::connect(Config::dataDir() . '/_system.sqlite');
    }

    private static function connect(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        // Journal par défaut (pas de WAL) : plus sûr sur les systèmes de fichiers réseau des mutualisés.
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        return $pdo;
    }

    private static function migrate(\PDO $pdo): void
    {
        if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() >= self::latestVersion()) {
            return;
        }
        $files = glob(APPC_ROOT . '/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (!preg_match('/^(\d+)_/', basename($file), $m)) {
                continue;
            }
            $version = (int) $m[1];
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                // Relu sous verrou : une requête concurrente a pu migrer entre-temps.
                if ($version > (int) $pdo->query('PRAGMA user_version')->fetchColumn()) {
                    $pdo->exec((string) file_get_contents($file));
                    $pdo->exec('PRAGMA user_version = ' . $version);
                }
                $pdo->exec('COMMIT');
            } catch (\Throwable $e) {
                $pdo->exec('ROLLBACK');
                throw $e;
            }
        }
    }

    private static function latestVersion(): int
    {
        $max = 0;
        foreach (glob(APPC_ROOT . '/migrations/*.sql') ?: [] as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max;
    }
}
