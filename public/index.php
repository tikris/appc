<?php
declare(strict_types=1);

// Serveur de développement (php -S) : laisse passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($path) && $path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

\Appc\App::run();
