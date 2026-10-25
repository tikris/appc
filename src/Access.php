<?php
declare(strict_types=1);

namespace Appc;

/**
 * Accès aux instances : codes d'accès (participants) et session administrateur.
 *
 * - Un participant saisit un code délivré par l'administrateur ; un jeton aléatoire est alors posé
 *   dans un cookie (seul son hachage SHA-256 est stocké). Aucune donnée personnelle n'est collectée.
 * - Révoquer un code coupe l'accès de tous les participants entrés avec ce code.
 * - L'expiration d'un code empêche seulement de nouvelles entrées.
 */
final class Access
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I, O, 0, 1
    private const PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    private const PARTICIPANT_TTL = 200 * 86400;
    private const ADMIN_TTL = 8 * 3600;

    public const MSG_BLOCKED = 'Trop d\'essais. Patientez 15 minutes avant de réessayer.';

    private const P_COOKIE = 'appc_p';
    private const A_COOKIE = 'appc_a';

    /** @var array<string, ?array> */
    private static array $participants = [];

    // ── Codes ───────────────────────────────────────────────────────────────

    public static function generateCode(): string
    {
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }
        return substr($out, 0, 4) . '-' . substr($out, 4);
    }

    /** Met en forme une saisie (casse, espaces, tirets) ; chaîne vide si le format est invalide. */
    public static function normalizeCode(string $raw): string
    {
        $c = preg_replace('/[^A-Z0-9]/', '', strtoupper($raw)) ?? '';
        return strlen($c) === 8 ? substr($c, 0, 4) . '-' . substr($c, 4) : '';
    }

    public static function generatePassword(): string
    {
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= self::PASSWORD_ALPHABET[random_int(0, strlen(self::PASSWORD_ALPHABET) - 1)];
        }
        return implode('-', str_split($out, 4));
    }

    /** @return array{id:int,code:string} */
    public static function createCode(Instance $in, string $label, ?int $maxUses, ?int $expiresAt): array
    {
        for ($try = 0; $try < 5; $try++) {
            $code = self::generateCode();
            try {
                $in->db->prepare('INSERT INTO codes (code, label, max_uses, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$code, $label, $maxUses, $expiresAt, time()]);
                return ['id' => (int) $in->db->lastInsertId(), 'code' => $code];
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Impossible de générer un code unique.');
    }

    /**
     * Tente d'entrer avec un code. Retourne null en cas de succès, sinon le message d'erreur à afficher.
     * Le message est volontairement identique pour un code inconnu, révoqué, expiré ou épuisé.
     */
    public static function join(Instance $in, string $raw): ?string
    {
        $limiter = new Limiter($in->db, 'code');
        if ($limiter->blocked()) {
            return self::MSG_BLOCKED;
        }

        $code = self::normalizeCode($raw);
        $token = bin2hex(random_bytes(32));
        $ok = false;

        $in->db->exec('BEGIN IMMEDIATE');
        try {
            if ($code !== '') {
                $st = $in->db->prepare('SELECT * FROM codes WHERE code = ? AND revoked_at IS NULL');
                $st->execute([$code]);
                $row = $st->fetch();
                if ($row && ($row['expires_at'] === null || (int) $row['expires_at'] > time())) {
                    $used = $in->db->prepare('SELECT COUNT(*) FROM participants WHERE code_id = ?');
                    $used->execute([$row['id']]);
                    if ($row['max_uses'] === null || (int) $used->fetchColumn() < (int) $row['max_uses']) {
                        $now = time();
                        $in->db->prepare('INSERT INTO participants (token_hash, code_id, created_at, last_seen_at) VALUES (?, ?, ?, ?)')
                            ->execute([hash('sha256', $token), $row['id'], $now, $now]);
                        $ok = true;
                    }
                }
            }
            $in->db->exec('COMMIT');
        } catch (\Throwable $e) {
            $in->db->exec('ROLLBACK');
            throw $e;
        }

        if (!$ok) {
            $limiter->fail();
            return 'Code invalide, expiré ou déjà utilisé. Vérifiez-le ou demandez-en un au représentant des parents.';
        }
        $limiter->clear();
        Http::setCookie(self::P_COOKIE, $token, self::PARTICIPANT_TTL);
        return null;
    }

    public static function leave(): void
    {
        Http::setCookie(self::P_COOKIE, '', -1);
    }

    /** Participant courant (null si absent ou si son code a été révoqué). */
    public static function participant(Instance $in): ?array
    {
        if (array_key_exists($in->slug, self::$participants)) {
            return self::$participants[$in->slug];
        }
        $row = null;
        $token = Http::cookie(self::P_COOKIE);
        if ($token !== null && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $st = $in->db->prepare(
                'SELECT p.* FROM participants p JOIN codes c ON c.id = p.code_id WHERE p.token_hash = ? AND c.revoked_at IS NULL'
            );
            $st->execute([hash('sha256', $token)]);
            $row = $st->fetch() ?: null;
            if ($row && (int) $row['last_seen_at'] < time() - 3600) {
                $in->db->prepare('UPDATE participants SET last_seen_at = ? WHERE id = ?')->execute([time(), $row['id']]);
            }
        }
        return self::$participants[$in->slug] = $row;
    }

    /**
     * Identifiant dérivé du jeton du navigateur pour un usage donné (ex. « signup »), sans lien calculable
     * avec participants.token_hash : la base seule ne permet pas de relier deux usages entre eux.
     * À n'appeler qu'après avoir vérifié que participant() n'est pas null.
     */
    public static function pseudonym(string $purpose): string
    {
        return hash('sha256', $purpose . '|' . (string) Http::cookie(self::P_COOKIE));
    }

    // ── Administrateur ──────────────────────────────────────────────────────

    public static function isAdmin(Instance $in): bool
    {
        $token = Http::cookie(self::A_COOKIE);
        if ($token === null || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $st = $in->db->prepare('SELECT 1 FROM admin_sessions WHERE token_hash = ? AND created_at > ?');
        $st->execute([hash('sha256', $token), time() - self::ADMIN_TTL]);
        return (bool) $st->fetchColumn();
    }

    public static function checkAdminPassword(Instance $in, string $password): bool
    {
        $hash = $in->setting('admin_hash', '') ?? '';
        return $hash !== '' && password_verify($password, $hash);
    }

    public static function startAdmin(Instance $in): void
    {
        $in->db->prepare('DELETE FROM admin_sessions WHERE created_at < ?')->execute([time() - self::ADMIN_TTL]);
        $token = bin2hex(random_bytes(32));
        $in->db->prepare('INSERT INTO admin_sessions (token_hash, created_at) VALUES (?, ?)')
            ->execute([hash('sha256', $token), time()]);
        Http::setCookie(self::A_COOKIE, $token, self::ADMIN_TTL);
    }

    public static function endAdmin(Instance $in): void
    {
        $token = Http::cookie(self::A_COOKIE);
        if ($token !== null) {
            $in->db->prepare('DELETE FROM admin_sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
        }
        Http::setCookie(self::A_COOKIE, '', -1);
    }

    /** Invalide toutes les sessions administrateur (après un changement de mot de passe). */
    public static function endAllAdminSessions(Instance $in): void
    {
        $in->db->exec('DELETE FROM admin_sessions');
    }

    // ── Super-administrateur (cookie signé sans état) ───────────────────────

    private const S_COOKIE = 'appc_s';
    private const SUPER_TTL = 2 * 3600;

    public static function isSuper(): bool
    {
        $v = Http::cookie(self::S_COOKIE);
        if ($v === null || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $v, $m)) {
            return false;
        }
        return (int) $m[1] > time() && hash_equals(self::superSignature((int) $m[1]), $m[2]);
    }

    public static function startSuper(): void
    {
        $exp = time() + self::SUPER_TTL;
        Http::setCookie(self::S_COOKIE, $exp . '.' . self::superSignature($exp), self::SUPER_TTL);
    }

    public static function endSuper(): void
    {
        Http::setCookie(self::S_COOKIE, '', -1);
    }

    private static function superSignature(int $exp): string
    {
        return hash_hmac('sha256', 'super|' . $exp, Config::secret());
    }
}
