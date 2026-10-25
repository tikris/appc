<?php
declare(strict_types=1);

// Usage : php bin/create-instance.php <identifiant> "<Nom affiché>" [modèle]
// Modèles : elementaire (par défaut), maternelle, college, vide — voir src/Templates.php.
// Exemple : php bin/create-instance.php ecole-etienne-marcel "École Étienne Marcel" elementaire
// Équivalent en ligne de commande de la console /_super.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

[, $slug, $name, $template] = $argv + [null, null, null, Appc\Templates::DEFAULT];
if (!is_string($slug) || !is_string($name)) {
    fwrite(STDERR, "Usage : php bin/create-instance.php <identifiant> \"<Nom affiché>\" [modèle]\n");
    exit(2);
}

try {
    $password = Appc\Access::generatePassword();
    Appc\Instance::create($slug, $name, $password, $template);
} catch (InvalidArgumentException | Appc\ConfigError $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Instance créée : /{$slug}/\n";
echo "Espace administrateur : /{$slug}/admin\n";
echo "Mot de passe provisoire (à changer à la première connexion) : {$password}\n";
