<?php
declare(strict_types=1);

// Usage : php bin/hash-password.php
// Demande un mot de passe et affiche son hash, à coller dans config/config.php (super_password_hash).
if (PHP_SAPI !== 'cli') {
    exit(1);
}

fwrite(STDERR, 'Mot de passe super-admin (12 caractères minimum) : ');
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    fwrite(STDERR, "Trop court.\n");
    exit(2);
}
echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
