<?php
declare(strict_types=1);

define('APPC_ROOT', dirname(__DIR__));

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Appc\\', 5) !== 0) {
        return;
    }
    $file = APPC_ROOT . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APPC_ROOT . '/src/helpers.php';
