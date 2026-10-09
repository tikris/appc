<?php
/**
 * Copiez ce fichier vers config/config.php (ignoré par git) puis adaptez-le.
 * NE COMMITEZ JAMAIS config.php : le dépôt est public.
 */
return [
    // Secret aléatoire d'au moins 32 caractères : hachage des adresses IP (limitation d'essais)
    // et signature de la session super-admin.
    // Générer : php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'app_secret' => '',

    // Hash du mot de passe super-admin (console /_super). Vide = console désactivée.
    // Générer : php bin/hash-password.php
    'super_password_hash' => '',

    // Dossier des bases SQLite (une par établissement). Idéalement hors du dossier web.
    'data_dir' => __DIR__ . '/../data',

    // Sous-dossier si l'application n'est pas à la racine du domaine ('' pour appc.paris/...).
    'base_path' => '',

    // null = détection automatique ; true force le drapeau Secure des cookies
    // (recommandé en production derrière HTTPS, notamment si l'hébergeur termine le TLS en amont).
    'secure_cookies' => null,

    // Nom de la variable $_SERVER portant l'IP réelle quand l'hébergeur place un proxy devant PHP
    // (ex. 'HTTP_X_FORWARDED_FOR'). À n'activer que si l'hébergeur garantit cet en-tête : sinon il est falsifiable.
    // null = REMOTE_ADDR.
    'ip_header' => null,

    // Messages d'erreur détaillés à l'écran. JAMAIS en production.
    'debug' => false,
];
