<?php
declare(strict_types=1);

namespace Appc;

/** Requêtes d'une instance : activités, consultations (thèmes, idées, votes, notes), annonces, codes. */
final class Repo
{
    /** Colonnes d'une activité modifiables par l'administrateur. */
    private const ACTIVITY_FIELDS = ['title', 'description', 'opens_at', 'closes_at', 'closed_at', 'published_at', 'max_ideas', 'multiple', 'results', 'show_names'];

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

    /** @return array<int,int> première colonne => seconde colonne */
    private function pairs(string $sql, array $params = []): array
    {
        $out = [];
        foreach ($this->all($sql, $params) as $r) {
            $r = array_values($r);
            $out[(int) $r[0]] = (int) $r[1];
        }
        return $out;
    }

    // ── Activités ───────────────────────────────────────────────────────────

    /** @return list<Activity> les plus récentes d'abord */
    public function activities(bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM activities' . ($publishedOnly ? ' WHERE published_at IS NOT NULL' : '') . ' ORDER BY id DESC';
        return array_map([Activity::class, 'fromRow'], $this->all($sql));
    }

    public function activity(int $id): ?Activity
    {
        $row = $this->one('SELECT * FROM activities WHERE id = ?', [$id]);
        return $row === null ? null : Activity::fromRow($row);
    }

    public function createActivity(string $kind, string $title, string $description = '', bool $publish = false): int
    {
        $now = time();
        $this->db->prepare('INSERT INTO activities (kind, title, description, published_at, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$kind, $title, $description, $publish ? $now : null, $now]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,int|string|null> $fields colonne => valeur (colonnes de ACTIVITY_FIELDS uniquement) */
    public function updateActivity(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::ACTIVITY_FIELDS));
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn(string $k): string => "$k = ?", array_keys($fields)));
        $this->db->prepare("UPDATE activities SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
    }

    /** Supprime l'activité et tout son contenu (thèmes, idées, votes, notes, options, réponses, créneaux, inscrits). */
    public function deleteActivity(int $id): void
    {
        $this->db->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
    }

    /**
     * Chiffres clés par activité, pour les listes.
     * @return array<int,array{ideas:int,votes:int,respondents:int,signups:int,capacity:int}>
     */
    public function activityCounts(): array
    {
        $ideas = $this->pairs('SELECT activity_id, COUNT(*) FROM ideas GROUP BY activity_id');
        $votes = $this->pairs('SELECT i.activity_id, COUNT(*) FROM votes v JOIN ideas i ON i.id = v.idea_id GROUP BY i.activity_id');
        $resp = $this->pairs('SELECT o.activity_id, COUNT(DISTINCT a.participant_id) FROM poll_answers a JOIN poll_options o ON o.id = a.option_id GROUP BY o.activity_id');
        $signups = $this->pairs('SELECT s.activity_id, COUNT(*) FROM signups g JOIN slots s ON s.id = g.slot_id GROUP BY s.activity_id');
        $capacity = $this->pairs('SELECT activity_id, SUM(capacity) FROM slots GROUP BY activity_id');
        $out = [];
        foreach ($this->db->query('SELECT id FROM activities')->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            $out[$id] = [
                'ideas'       => $ideas[$id] ?? 0,
                'votes'       => $votes[$id] ?? 0,
                'respondents' => $resp[$id] ?? 0,
                'signups'     => $signups[$id] ?? 0,
                'capacity'    => $capacity[$id] ?? 0,
            ];
        }
        return $out;
    }

    // ── Thèmes (par consultation) ───────────────────────────────────────────

    public function themes(int $activityId): array
    {
        return $this->all('SELECT * FROM themes WHERE activity_id = ? ORDER BY position, id', [$activityId]);
    }

    public function theme(int $activityId, int $id): ?array
    {
        return $this->one('SELECT * FROM themes WHERE id = ? AND activity_id = ?', [$id, $activityId]);
    }

    /** @return array<int,int> theme_id => nombre d'idées */
    public function ideaCountsByTheme(int $activityId): array
    {
        return $this->pairs('SELECT theme_id, COUNT(*) FROM ideas WHERE activity_id = ? GROUP BY theme_id', [$activityId]);
    }

    public function addTheme(int $activityId, string $name, int $color): void
    {
        $pos = (int) $this->one('SELECT COALESCE(MAX(position), 0) + 1 AS p FROM themes WHERE activity_id = ?', [$activityId])['p'];
        $this->db->prepare('INSERT INTO themes (activity_id, name, color, position) VALUES (?, ?, ?, ?)')
            ->execute([$activityId, $name, $color, $pos]);
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

    /** Idées d'une consultation, classées par nombre de votes décroissant, puis par ancienneté. */
    public function ideas(int $activityId, ?int $themeId, int $viewerId): array
    {
        return $this->all(
            'SELECT i.*, t.name AS theme_name, t.color AS theme_color,
                    (SELECT COUNT(*) FROM votes v WHERE v.idea_id = i.id) AS vote_count,
                    EXISTS (SELECT 1 FROM votes v WHERE v.idea_id = i.id AND v.participant_id = :me) AS voted
             FROM ideas i JOIN themes t ON t.id = i.theme_id
             WHERE i.activity_id = :a AND (:theme IS NULL OR i.theme_id = :theme)
             ORDER BY vote_count DESC, i.created_at ASC, i.id ASC',
            [':a' => $activityId, ':me' => $viewerId, ':theme' => $themeId]
        );
    }

    public function idea(int $activityId, int $id): ?array
    {
        return $this->one('SELECT * FROM ideas WHERE id = ? AND activity_id = ?', [$id, $activityId]);
    }

    public function countIdeasBy(int $activityId, int $participantId): int
    {
        return (int) $this->one('SELECT COUNT(*) AS n FROM ideas WHERE activity_id = ? AND participant_id = ?', [$activityId, $participantId])['n'];
    }

    public function addIdea(int $activityId, int $themeId, string $title, string $description, int $participantId): int
    {
        $this->db->prepare(
            'INSERT INTO ideas (activity_id, theme_id, title, description, participant_id, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$activityId, $themeId, $title, $description, $participantId, time()]);
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

    public function note(int $activityId, int $id): ?array
    {
        return $this->one(
            'SELECT n.* FROM notes n JOIN ideas i ON i.id = n.idea_id WHERE n.id = ? AND i.activity_id = ?',
            [$id, $activityId]
        );
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

    /** Export CSV d'une consultation : une ligne par idée, notes de contexte concaténées. */
    public function exportIdeas(int $activityId): array
    {
        $notes = [];
        foreach ($this->all(
            'SELECT n.idea_id, n.body FROM notes n JOIN ideas i ON i.id = n.idea_id WHERE i.activity_id = ? ORDER BY n.created_at, n.id',
            [$activityId]
        ) as $n) {
            $notes[(int) $n['idea_id']][] = $n['body'];
        }
        $rows = [];
        foreach ($this->ideas($activityId, null, 0) as $i) {
            $rows[] = [
                $i['id'], $i['theme_name'], $i['title'], $i['description'], $i['vote_count'],
                date('Y-m-d H:i', (int) $i['created_at']),
                $i['updated_at'] ? date('Y-m-d H:i', (int) $i['updated_at']) : '',
                implode(' | ', $notes[(int) $i['id']] ?? []),
            ];
        }
        return $rows;
    }

    // ── Annonces et agenda ──────────────────────────────────────────────────

    /** Annonces épinglées d'abord, puis les plus récentes. */
    public function posts(): array
    {
        return $this->all('SELECT * FROM posts ORDER BY pinned DESC, created_at DESC, id DESC');
    }

    public function post(int $id): ?array
    {
        return $this->one('SELECT * FROM posts WHERE id = ?', [$id]);
    }

    /** Annonces datées à partir de $from, la plus proche d'abord. */
    public function upcomingEvents(int $from): array
    {
        return $this->all('SELECT * FROM posts WHERE event_at >= ? ORDER BY event_at, id', [$from]);
    }

    /** @param array{title:string,body:string,event_at:?int,location:string,pinned:bool} $p */
    public function savePost(?int $id, array $p): int
    {
        $values = [$p['title'], $p['body'], $p['event_at'], $p['location'], $p['pinned'] ? 1 : 0, time()];
        if ($id === null) {
            $this->db->prepare('INSERT INTO posts (title, body, event_at, location, pinned, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute($values);
            return (int) $this->db->lastInsertId();
        }
        $this->db->prepare('UPDATE posts SET title = ?, body = ?, event_at = ?, location = ?, pinned = ?, updated_at = ? WHERE id = ?')
            ->execute([...$values, $id]);
        return $id;
    }

    public function deletePost(int $id): void
    {
        $this->db->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    // ── Codes d'accès ───────────────────────────────────────────────────────

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

    /** @return array{activities:int,posts:int,participants:int,ideas:int,votes:int} */
    public function stats(): array
    {
        $n = fn(string $t): int => (int) $this->db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        return [
            'activities'   => $n('activities'),
            'posts'        => $n('posts'),
            'participants' => $n('participants'),
            'ideas'        => $n('ideas'),
            'votes'        => $n('votes'),
        ];
    }
}
