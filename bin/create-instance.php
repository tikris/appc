<?php
declare(strict_types=1);

// Usage : php bin/create-instance.php <identifiant> "<Nom affiché>"
// Exemple : php bin/create-instance.php master "Portail maître APPC"
// Équivalent en ligne de commande de la console /_super.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

[, $slug, $name] = $argv + [null, null, null];
if (!is_string($slug) || !is_string($name)) {
    fwrite(STDERR, "Usage : php bin/create-instance.php <identifiant> \"<Nom affiché>\"\n");
    exit(2);
}

try {
    $password = Appc\Access::generatePassword();
    Appc\Instance::create($slug, $name, $password);
} catch (InvalidArgumentException | Appc\ConfigError $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Instance créée : /{$slug}/\n";
echo "Espace administrateur : /{$slug}/admin\n";
echo "Mot de passe provisoire (à changer à la première connexion) : {$password}\n";
