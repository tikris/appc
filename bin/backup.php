<?php
declare(strict_types=1);

// Usage : php bin/backup.php <dossier-de-sauvegarde> [nombre-de-copies-à-conserver=14]
// Crée une copie cohérente de chaque instance (VACUUM INTO, sûr même si le site est en ligne)
// et ne conserve que les N plus récentes par instance.
// À planifier en tâche cron quotidienne. Le dossier de sauvegarde doit être hors du dossier web.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

$dest = $argv[1] ?? null;
$keep = max(1, (int) ($argv[2] ?? 14));
if (!is_string($dest) || $dest === '') {
    fwrite(STDERR, "Usage : php bin/backup.php <dossier-de-sauvegarde> [copies=14]\n");
    exit(2);
}
if (!is_dir($dest) && !mkdir($dest, 0700, true) && !is_dir($dest)) {
    fwrite(STDERR, "Dossier de sauvegarde inaccessible : $dest\n");
    exit(1);
}
$dest = rtrim($dest, '/');
$stamp = date('Ymd-His');
$failed = false;

foreach (Appc\Instance::allSlugs() as $slug) {
    $target = "$dest/$slug-$stamp.sqlite";
    try {
        $in = Appc\Instance::load($slug);
        if ($in === null) {
            continue;
        }
        $in->db->exec('VACUUM INTO ' . $in->db->quote($target));
        chmod($target, 0600);
        echo "OK   $slug → $target\n";
    } catch (Throwable $e) {
        $failed = true;
        fwrite(STDERR, "ECHEC $slug : " . $e->getMessage() . "\n");
        continue;
    }
    $old = glob("$dest/$slug-*.sqlite") ?: [];
    rsort($old);
    foreach (array_slice($old, $keep) as $file) {
        unlink($file);
    }
}
exit($failed ? 1 : 0);
