<?php
declare(strict_types=1);

namespace Appc;

/**
 * Protection CSRF par « double soumission de cookie » : le jeton est dans un cookie
 * HttpOnly SameSite=Lax et dans un champ caché ; les deux doivent coïncider.
 */
final class Csrf
{
    private const COOKIE = 'appc_csrf';
    private static ?string $token = null;

    public static function token(): string
    {
        if (self::$token !== null) {
            return self::$token;
        }
        $cookie = Http::cookie(self::COOKIE);
        if ($cookie === null || !preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            $cookie = bin2hex(random_bytes(32));
            Http::setCookie(self::COOKIE, $cookie, 0);
        }
        return self::$token = $cookie;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::token()) . '">';
    }

    public static function check(): void
    {
        $cookie = Http::cookie(self::COOKIE);
        $posted = $_POST['_csrf'] ?? null;
        if ($cookie === null || !is_string($posted) || !hash_equals($cookie, $posted)) {
            abort(403, 'Session expirée ou formulaire invalide : rechargez la page et réessayez.');
        }
    }
}
