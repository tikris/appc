<?php
declare(strict_types=1);

namespace Appc;

/** Requêtes des sondages : options et réponses des participants. */
final class PollRepo
{
    public function __construct(private \PDO $db)
    {
    }

    /** Options dans l'ordre, avec leur nombre de réponses. */
    public function options(int $activityId): array
    {
        $st = $this->db->prepare(
            'SELECT o.*, (SELECT COUNT(*) FROM poll_answers a WHERE a.option_id = o.id) AS answers
             FROM poll_options o WHERE o.activity_id = ? ORDER BY o.position, o.id'
        );
        $st->execute([$activityId]);
        return $st->fetchAll();
    }

    public function option(int $activityId, int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM poll_options WHERE id = ? AND activity_id = ?');
        $st->execute([$id, $activityId]);
        return $st->fetch() ?: null;
    }

    /** Nombre de participants ayant répondu. */
    public function respondents(int $activityId): int
    {
        $st = $this->db->prepare(
            'SELECT COUNT(DISTINCT a.participant_id) FROM poll_answers a JOIN poll_options o ON o.id = a.option_id WHERE o.activity_id = ?'
        );
        $st->execute([$activityId]);
        return (int) $st->fetchColumn();
    }

    /** @return list<int> options cochées par ce participant */
    public function answersOf(int $activityId, int $participantId): array
    {
        $st = $this->db->prepare(
            'SELECT a.option_id FROM poll_answers a JOIN poll_options o ON o.id = a.option_id WHERE o.activity_id = ? AND a.participant_id = ?'
        );
        $st->execute([$activityId, $participantId]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Remplace les réponses du participant (les options doivent avoir été vérifiées). */
    public function answer(int $activityId, int $participantId, array $optionIds): void
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->db->prepare(
                'DELETE FROM poll_answers WHERE participant_id = ? AND option_id IN (SELECT id FROM poll_options WHERE activity_id = ?)'
            )->execute([$participantId, $activityId]);
            $ins = $this->db->prepare('INSERT INTO poll_answers (option_id, participant_id, created_at) VALUES (?, ?, ?)');
            foreach ($optionIds as $id) {
                $ins->execute([$id, $participantId, time()]);
            }
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function addOption(int $activityId, string $label): void
    {
        $st = $this->db->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM poll_options WHERE activity_id = ?');
        $st->execute([$activityId]);
        $this->db->prepare('INSERT INTO poll_options (activity_id, label, position) VALUES (?, ?, ?)')
            ->execute([$activityId, $label, (int) $st->fetchColumn()]);
    }

    public function updateOption(int $id, string $label, int $position): void
    {
        $this->db->prepare('UPDATE poll_options SET label = ?, position = ? WHERE id = ?')->execute([$label, $position, $id]);
    }

    public function deleteOption(int $id): void
    {
        $this->db->prepare('DELETE FROM poll_options WHERE id = ?')->execute([$id]);
    }

    /** Export CSV : une ligne par option. */
    public function exportRows(int $activityId): array
    {
        $total = $this->respondents($activityId);
        return array_map(static fn(array $o): array => [
            $o['label'], $o['answers'], $total > 0 ? round(100 * (int) $o['answers'] / $total) . ' %' : '',
        ], $this->options($activityId));
    }
}
