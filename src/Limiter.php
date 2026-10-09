<?php
declare(strict_types=1);

namespace Appc;

/**
 * Limitation des essais par adresse IP (hachée) : 8 échecs par fenêtre de 15 minutes.
 * Volontairement sans plafond global : il permettrait de verrouiller tout le monde à distance.
 */
final class Limiter
{
    public function __construct(
        private \PDO $db,
        private string $kind,
        private int $max = 8,
        private int $window = 900,
    ) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS attempts (id INTEGER PRIMARY KEY, kind TEXT NOT NULL, ip_hash TEXT NOT NULL, at INTEGER NOT NULL);'
            . 'CREATE INDEX IF NOT EXISTS attempts_lookup ON attempts(kind, ip_hash, at)'
        );
    }

    public function blocked(): bool
    {
        $this->db->prepare('DELETE FROM attempts WHERE at < ?')->execute([time() - 86400]);
        $st = $this->db->prepare('SELECT COUNT(*) FROM attempts WHERE kind = ? AND ip_hash = ? AND at > ?');
        $st->execute([$this->kind, Http::ipHash(), time() - $this->window]);
        return (int) $st->fetchColumn() >= $this->max;
    }

    public function fail(): void
    {
        $this->db->prepare('INSERT INTO attempts (kind, ip_hash, at) VALUES (?, ?, ?)')
            ->execute([$this->kind, Http::ipHash(), time()]);
    }

    public function clear(): void
    {
        $this->db->prepare('DELETE FROM attempts WHERE kind = ? AND ip_hash = ?')
            ->execute([$this->kind, Http::ipHash()]);
    }
}
