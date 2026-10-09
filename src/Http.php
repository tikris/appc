<?php
declare(strict_types=1);

namespace Appc;

final class Http
{
    /** Chemin des cookies de la section courante (ex. /master ou /_super), défini par le routeur. */
    public static string $cookiePath = '/';

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Chemin demandé, relatif à base_path, tel qu'envoyé (avec son éventuel slash final). */
    public static function rawPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rawurldecode(is_string($path) ? $path : '/');
        $base = Config::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return '/' . ltrim($path, '/');
    }

    /** @return list<string> */
    public static function segments(): array
    {
        return array_values(array_filter(explode('/', self::rawPath()), static fn(string $s): bool => $s !== ''));
    }

    public static function cookie(string $name): ?string
    {
        $v = $_COOKIE[$name] ?? null;
        return is_string($v) ? $v : null;
    }

    /** $ttl > 0 : durée en secondes ; 0 : cookie de session ; < 0 : suppression. */
    public static function setCookie(string $name, string $value, int $ttl): void
    {
        setcookie($name, $ttl < 0 ? '' : $value, [
            'expires'  => $ttl > 0 ? time() + $ttl : ($ttl < 0 ? time() - 3600 : 0),
            'path'     => self::$cookiePath,
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function isHttps(): bool
    {
        $forced = Config::get('secure_cookies');
        if (is_bool($forced)) {
            return $forced;
        }
        $https = $_SERVER['HTTPS'] ?? '';
        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    /** Adresse IP du client. N'utilise ip_header que si l'hébergeur le garantit (sinon il est falsifiable). */
    public static function ip(): string
    {
        $header = Config::get('ip_header');
        if (is_string($header) && $header !== '' && !empty($_SERVER[$header])) {
            $parts = array_map('trim', explode(',', (string) $_SERVER[$header]));
            $ip = end($parts); // dernière entrée : celle ajoutée par le proxy de confiance
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function ipHash(): string
    {
        return hash_hmac('sha256', self::ip(), Config::secret());
    }

    public static function redirect(string $to, int $status = 303): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    public static function securityHeaders(): void
    {
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }
}
