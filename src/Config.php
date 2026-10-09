<?php
declare(strict_types=1);

namespace Appc;

final class Config
{
    private static ?array $config = null;

    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $file = getenv('APPC_CONFIG') ?: APPC_ROOT . '/config/config.php';
        if (!is_file($file)) {
            throw new ConfigError('Configuration manquante : copiez config/config.example.php vers config/config.php.');
        }
        $c = require $file;
        if (!is_array($c)) {
            throw new ConfigError('config.php doit retourner un tableau.');
        }
        if (!is_string($c['app_secret'] ?? null) || strlen($c['app_secret']) < 32) {
            throw new ConfigError('config.php : app_secret doit contenir au moins 32 caractères.');
        }
        return self::$config = $c + [
            'super_password_hash' => '',
            'data_dir'            => APPC_ROOT . '/data',
            'base_path'           => '',
            'secure_cookies'      => null,
            'ip_header'           => null,
            'debug'               => false,
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::load()[$key] ?? $default;
    }

    public static function debug(): bool
    {
        try {
            return (bool) self::get('debug', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function secret(): string
    {
        return (string) self::get('app_secret');
    }

    public static function basePath(): string
    {
        try {
            return rtrim((string) self::get('base_path', ''), '/');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function dataDir(): string
    {
        $dir = rtrim((string) self::get('data_dir'), '/');
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new ConfigError('Le dossier de données est introuvable et ne peut pas être créé.');
        }
        return $dir;
    }
}
