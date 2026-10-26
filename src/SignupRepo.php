<?php
declare(strict_types=1);

namespace Appc;

/**
 * Requêtes des inscriptions bénévoles : créneaux et inscrits.
 * Un inscrit est repéré par owner_hash (voir Access::pseudonym), jamais par son participant.
 */
final class SignupRepo
{
    public const NAME_MAX = 40;

    public function __construct(private \PDO $db)
    {
    }

    private function all(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Créneaux dans l'ordre choisi par l'administrateur, avec le nombre d'inscrits. */
    public function slots(int $activityId): array
    {
        return $this->all(
            'SELECT s.*, (SELECT COUNT(*) FROM signups g WHERE g.slot_id = s.id) AS taken
             FROM slots s WHERE s.activity_id = ? ORDER BY s.position, s.id',
            [$activityId]
        );
    }

    public function slot(int $activityId, int $id): ?array
    {
        return $this->all('SELECT * FROM slots WHERE id = ? AND activity_id = ?', [$id, $activityId])[0] ?? null;
    }

    /** @return array<int,list<array>> slot_id => inscrits (ordre d'inscription) */
    public function signupsBySlot(int $activityId): array
    {
        $out = [];
        foreach ($this->all(
            'SELECT g.* FROM signups g JOIN slots s ON s.id = g.slot_id WHERE s.activity_id = ? ORDER BY g.created_at, g.id',
            [$activityId]
        ) as $g) {
            $out[(int) $g['slot_id']][] = $g;
        }
        return $out;
    }

    public function signup(int $activityId, int $id): ?array
    {
        return $this->all(
            'SELECT g.* FROM signups g JOIN slots s ON s.id = g.slot_id WHERE g.id = ? AND s.activity_id = ?',
            [$id, $activityId]
        )[0] ?? null;
    }

    /** @return list<int> créneaux de l'activité où ce navigateur est inscrit */
    public function mySlotIds(int $activityId, string $ownerHash): array
    {
        return array_map(static fn(array $r): int => (int) $r['slot_id'], $this->all(
            'SELECT g.slot_id FROM signups g JOIN slots s ON s.id = g.slot_id WHERE s.activity_id = ? AND g.owner_hash = ?',
            [$activityId, $ownerHash]
        ));
    }

    /** Dernier prénom saisi depuis ce navigateur (pour préremplir le formulaire). */
    public function lastName(string $ownerHash): string
    {
        return (string) ($this->all('SELECT name FROM signups WHERE owner_hash = ? ORDER BY id DESC LIMIT 1', [$ownerHash])[0]['name'] ?? '');
    }

    /** Inscrit sur un créneau ; retourne null en cas de succès, sinon le message d'erreur. */
    public function join(array $slot, string $ownerHash, string $name): ?string
    {
        $this->db->exec('BEGIN IMMEDIATE'); // compte et insertion sous verrou : la capacité ne peut pas être dépassée
        try {
            $taken = (int) $this->all('SELECT COUNT(*) AS n FROM signups WHERE slot_id = ?', [$slot['id']])[0]['n'];
            $already = $this->all('SELECT 1 FROM signups WHERE slot_id = ? AND owner_hash = ?', [$slot['id'], $ownerHash]) !== [];
            $error = $already ? 'Vous êtes déjà inscrit(e) sur ce créneau.'
                : ($taken >= (int) $slot['capacity'] ? 'Ce créneau est complet.' : null);
            if ($error === null) {
                // Date arrondie au jour : une heure précise permettrait de recouper l'inscription avec les votes
                // et réponses horodatés du même participant (voir SECURITE-RGPD).
                $this->db->prepare('INSERT INTO signups (slot_id, owner_hash, name, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$slot['id'], $ownerHash, $name, (int) strtotime('today')]);
            }
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
        return $error;
    }

    public function leave(int $slotId, string $ownerHash): void
    {
        $this->db->prepare('DELETE FROM signups WHERE slot_id = ? AND owner_hash = ?')->execute([$slotId, $ownerHash]);
    }

    public function deleteSignup(int $id): void
    {
        $this->db->prepare('DELETE FROM signups WHERE id = ?')->execute([$id]);
    }

    public function addSlot(int $activityId, string $label, ?int $startsAt, int $capacity): void
    {
        $pos = (int) ($this->all('SELECT COALESCE(MAX(position), 0) + 1 AS p FROM slots WHERE activity_id = ?', [$activityId])[0]['p']);
        $this->db->prepare('INSERT INTO slots (activity_id, label, starts_at, capacity, position) VALUES (?, ?, ?, ?, ?)')
            ->execute([$activityId, $label, $startsAt, $capacity, $pos]);
    }

    public function updateSlot(int $id, string $label, ?int $startsAt, int $capacity, int $position): void
    {
        $this->db->prepare('UPDATE slots SET label = ?, starts_at = ?, capacity = ?, position = ? WHERE id = ?')
            ->execute([$label, $startsAt, $capacity, $position, $id]);
    }

    public function deleteSlot(int $id): void
    {
        $this->db->prepare('DELETE FROM slots WHERE id = ?')->execute([$id]); // inscrits en cascade
    }

    /** Export CSV : une ligne par inscrit, puis une ligne par place libre. */
    public function exportRows(int $activityId): array
    {
        $by = $this->signupsBySlot($activityId);
        $rows = [];
        foreach ($this->slots($activityId) as $s) {
            $when = $s['starts_at'] !== null ? date('Y-m-d H:i', (int) $s['starts_at']) : '';
            foreach ($by[(int) $s['id']] ?? [] as $g) {
                $rows[] = [$s['label'], $when, $g['name'], date('Y-m-d', (int) $g['created_at'])];
            }
            for ($i = (int) $s['taken']; $i < (int) $s['capacity']; $i++) {
                $rows[] = [$s['label'], $when, '(place libre)', ''];
            }
        }
        return $rows;
    }
}
