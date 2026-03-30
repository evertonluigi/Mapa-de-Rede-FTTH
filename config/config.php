<?php
// ── Carregar .env ──────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// ── Constantes da aplicação ────────────────────────────────────────────────────
define('APP_NAME',    'FTTH Network Manager');
define('APP_VERSION', '1.0.0');

// BASE_URL: auto-detecta subdomínio (BASE_URL='') ou subpasta ('/interno/sistemas/mapa')
if (!defined('BASE_URL')) {
    $appRootFs = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRootFs = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRootFs && strpos($appRootFs, $docRootFs) === 0) {
        $base = rtrim(substr($appRootFs, strlen($docRootFs)), '/');
    } else {
        $base = '/mapas'; // fallback dev
    }
    define('BASE_URL', $base);
}

define('SESSION_LIFETIME', 3600 * 8);
define('GOOGLE_MAPS_KEY',  $_ENV['GOOGLE_MAPS_KEY'] ?? '');
define('JWT_SECRET',       $_ENV['JWT_SECRET']       ?? 'change-me');
define('JWT_EXPIRY',       3600 * 24 * 30);

// ── Timezone e sessão ──────────────────────────────────────────────────────────
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

// ── Autoload ───────────────────────────────────────────────────────────────────
spl_autoload_register(function ($class) {
    foreach ([__DIR__ . '/../includes/', __DIR__ . '/../modules/'] as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});

require_once __DIR__ . '/database.php';
