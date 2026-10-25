<?php
declare(strict_types=1);

/** Échappement HTML (à utiliser pour toute sortie dynamique). */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absolue (chemin) dans l'application, en tenant compte de base_path. */
function url(string $path = ''): string
{
    return \Appc\Config::basePath() . '/' . ltrim($path, '/');
}

function fmt_date(int $ts): string
{
    return date('d/m/Y', $ts);
}

function fmt_datetime(int $ts): string
{
    return date('d/m/Y \à H\hi', $ts);
}

/** Date courte en français pour l'agenda : « mar. 12 nov. », suivie de l'heure si elle n'est pas minuit. */
function fmt_day(int $ts): string
{
    $days = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $out = $days[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1];
    return date('H:i', $ts) === '00:00' ? $out : $out . ' à ' . date('G\hi', $ts);
}

/** Valeur pour <input type="datetime-local"> (heure de Paris). */
function input_datetime(?int $ts): string
{
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/** Interprète la valeur d'un <input type="datetime-local"> ; null si vide ou invalide. */
function parse_datetime(string $v): ?int
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $v, new DateTimeZone('Europe/Paris'));
    return $d === false ? null : $d->getTimestamp();
}

/** Nettoie un texte saisi : UTF-8 valide, sauts de ligne normalisés, caractères de contrôle retirés. */
function clean_text(mixed $s): string
{
    $s = is_string($s) ? $s : '';
    if (!mb_check_encoding($s, 'UTF-8')) {
        return '';
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return trim($s);
}

/** Champs cachés d'un formulaire POST : jeton CSRF, action et paramètres. */
function post_fields(string $action, array $fields = []): string
{
    $out = \Appc\Csrf::field() . '<input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($fields as $k => $v) {
        $out .= '<input type="hidden" name="' . h((string) $k) . '" value="' . h((string) $v) . '">';
    }
    return $out;
}

function abort(int $status, string $message = ''): never
{
    throw new \Appc\HttpError($status, $message);
}

/**
 * Rend une vue dans le gabarit commun, puis termine la requête.
 * Les variables passées ne doivent pas s'appeler view, vars, status ou content.
 */
function render(string $view, array $vars = [], int $status = 200): never
{
    http_response_code($status);
    \Appc\Http::securityHeaders();
    header('Content-Type: text/html; charset=UTF-8');
    extract($vars, EXTR_SKIP);
    ob_start();
    require APPC_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APPC_ROOT . '/views/layout.php';
    exit;
}

/**
 * Envoie un fichier CSV (séparateur « ; », UTF-8 avec BOM : lisible par Excel et LibreOffice), puis termine la requête.
 * Les cellules commençant par = + - @ sont préfixées d'une apostrophe (injection de formules).
 */
function send_csv(string $filename, array $header, array $rows): never
{
    $safe = static function (mixed $v): string {
        $s = (string) $v;
        return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'" . $s : $s;
    };
    \Appc\Http::securityHeaders();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $header, ';', '"', '', "\r\n");
    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, $row), ';', '"', '', "\r\n");
    }
    fclose($out);
    exit;
}
