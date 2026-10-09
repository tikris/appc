<?php
declare(strict_types=1);

namespace Appc;

/** Requêtes de lecture/écriture des idées, votes et notes d'une instance. */
final class Repo
{
    public function __construct(private \PDO $db)
    {
    }

    private function all(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    private function one(string $sql, array $params = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    // ── Thèmes ──────────────────────────────────────────────────────────────

    public function themes(): array
    {
        return $this->all('SELECT * FROM themes ORDER BY position, id');
    }

    public function theme(int $id): ?array
    {
        return $this->one('SELECT * FROM themes WHERE id = ?', [$id]);
    }

    /** @return array<int,int> theme_id => nombre d'idées */
    public function ideaCountsByTheme(): array
    {
        $out = [];
        foreach ($this->all('SELECT theme_id, COUNT(*) AS n FROM ideas GROUP BY theme_id') as $r) {
            $out[(int) $r['theme_id']] = (int) $r['n'];
        }
        return $out;
    }

    public function addTheme(string $name, int $color): void
    {
        $pos = (int) $this->db->query('SELECT COALESCE(MAX(position), 0) + 1 FROM themes')->fetchColumn();
        $this->db->prepare('INSERT INTO themes (name, color, position) VALUES (?, ?, ?)')->execute([$name, $color, $pos]);
    }

    public function updateTheme(int $id, string $name, int $color, int $position): void
    {
        $this->db->prepare('UPDATE themes SET name = ?, color = ?, position = ? WHERE id = ?')
            ->execute([$name, $color, $position, $id]);
    }

    public function deleteTheme(int $id): void
    {
        $this->db->prepare('DELETE FROM themes WHERE id = ?')->execute([$id]);
    }

    // ── Idées ───────────────────────────────────────────────────────────────

    /** Idées classées par nombre de votes décroissant, puis par ancienneté. */
    public function ideas(?int $themeId, int $viewerId): array
    {
        return $this->all(
            'SELECT i.*, t.name AS theme_name, t.color AS theme_color,
                    (SELECT COUNT(*) FROM votes v WHERE v.idea_id = i.id) AS vote_count,
                    EXISTS (SELECT 1 FROM votes v WHERE v.idea_id = i.id AND v.participant_id = :me) AS voted
             FROM ideas i JOIN themes t ON t.id = i.theme_id
             WHERE (:theme IS NULL OR i.theme_id = :theme)
             ORDER BY vote_count DESC, i.created_at ASC, i.id ASC',
            [':me' => $viewerId, ':theme' => $themeId]
        );
    }

    public function idea(int $id): ?array
    {
        return $this->one('SELECT * FROM ideas WHERE id = ?', [$id]);
    }

    public function countIdeasBy(int $participantId): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS n FROM ideas WHERE participant_id = ?', [$participantId])['n'] ?? 0);
    }

    public function addIdea(int $themeId, string $title, string $description, int $participantId): int
    {
        $this->db->prepare(
            'INSERT INTO ideas (theme_id, title, description, participant_id, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$themeId, $title, $description, $participantId, time()]);
        return (int) $this->db->lastInsertId();
    }

    public function updateIdea(int $id, int $themeId, string $title, string $description): void
    {
        $this->db->prepare('UPDATE ideas SET theme_id = ?, title = ?, description = ?, updated_at = ? WHERE id = ?')
            ->execute([$themeId, $title, $description, time(), $id]);
    }

    public function deleteIdea(int $id): void
    {
        $this->db->prepare('DELETE FROM ideas WHERE id = ?')->execute([$id]); // votes et notes en cascade
    }

    public function vote(int $ideaId, int $participantId): void
    {
        $this->db->prepare('INSERT OR IGNORE INTO votes (idea_id, participant_id, created_at) VALUES (?, ?, ?)')
            ->execute([$ideaId, $participantId, time()]);
    }

    // ── Notes de contexte ───────────────────────────────────────────────────

    /** @return array<int,list<array>> idea_id => notes (plus anciennes d'abord) */
    public function notesByIdea(array $ideaIds): array
    {
        if ($ideaIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ideaIds), '?'));
        $out = [];
        foreach ($this->all("SELECT * FROM notes WHERE idea_id IN ($in) ORDER BY created_at, id", array_values($ideaIds)) as $n) {
            $out[(int) $n['idea_id']][] = $n;
        }
        return $out;
    }

    public function note(int $id): ?array
    {
        return $this->one('SELECT * FROM notes WHERE id = ?', [$id]);
    }

    public function saveNote(int $ideaId, int $participantId, string $body): void
    {
        $now = time();
        $this->db->prepare(
            'INSERT INTO notes (idea_id, participant_id, body, created_at) VALUES (?, ?, ?, ?)
             ON CONFLICT(idea_id, participant_id) DO UPDATE SET body = excluded.body, updated_at = ?'
        )->execute([$ideaId, $participantId, $body, $now, $now]);
    }

    public function deleteNote(int $id): void
    {
        $this->db->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
    }

    // ── Administration ──────────────────────────────────────────────────────

    public function codes(): array
    {
        return $this->all(
            'SELECT c.*, (SELECT COUNT(*) FROM participants p WHERE p.code_id = c.id) AS uses
             FROM codes c ORDER BY c.created_at DESC, c.id DESC'
        );
    }

    public function revokeCode(int $id): void
    {
        $this->db->prepare('UPDATE codes SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL')->execute([time(), $id]);
    }

    /** @return array{ideas:int,votes:int,participants:int,notes:int} */
    public function stats(): array
    {
        $n = static fn(\PDO $db, string $t): int => (int) $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        return [
            'ideas'        => $n($this->db, 'ideas'),
            'votes'        => $n($this->db, 'votes'),
            'participants' => $n($this->db, 'participants'),
            'notes'        => $n($this->db, 'notes'),
        ];
    }

    /** Export CSV : une ligne par idée, notes de contexte concaténées. */
    public function exportRows(): array
    {
        $notes = [];
        foreach ($this->all('SELECT idea_id, body FROM notes ORDER BY created_at, id') as $n) {
            $notes[(int) $n['idea_id']][] = $n['body'];
        }
        $rows = [];
        foreach ($this->ideas(null, 0) as $i) {
            $rows[] = [
                $i['id'], $i['theme_name'], $i['title'], $i['description'], $i['vote_count'],
                date('Y-m-d H:i', (int) $i['created_at']),
                $i['updated_at'] ? date('Y-m-d H:i', (int) $i['updated_at']) : '',
                implode(' | ', $notes[(int) $i['id']] ?? []),
            ];
        }
        return $rows;
    }
}
