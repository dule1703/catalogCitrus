<?php

namespace App;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use RuntimeException;

require_once __DIR__ . '/vendor/autoload.php';

// ✅ Učitavanje .env fajla
$envFile = '.env.' . ($_ENV['APP_ENV'] ?? 'local');
if (file_exists(__DIR__ . '/' . $envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__, $envFile);
} else {
    $dotenv = Dotenv::createImmutable(__DIR__);
}
$dotenv->load();

// ✅ Postavke okruženja
if (($_ENV['APP_DEBUG'] ?? false) === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

if (!empty($_ENV['APP_TIMEZONE'])) {
    date_default_timezone_set($_ENV['APP_TIMEZONE']);
}

if (!empty($_ENV['APP_LOCALE'])) {
    setlocale(LC_ALL, $_ENV['APP_LOCALE']);
}

// ✅ Provera .env varijabli
$requiredEnvVars = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    // 'DB_PASS', // odkomentariši u produkciji
    'REDIS_HOST',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_FROM_ADDRESS',
];

$missing = array_filter($requiredEnvVars, function($var) {
    return empty($_ENV[$var]);
});

if (!empty($missing)) {
    throw new RuntimeException('Nedostaju obavezne .env promenljive: ' . implode(', ', $missing));
}

// ✅ Kreiranje DI kontejnera
$builder = new ContainerBuilder();

if (($_ENV['APP_ENV'] ?? 'local') === 'production') {
    $diCacheDir = __DIR__ . '/cache/di/' . ($_ENV['APP_ENV'] ?? 'production');
    if (!is_dir($diCacheDir)) {
        mkdir($diCacheDir, 0755, true);
    }
    $builder->enableCompilation($diCacheDir);
}

// ✅ Učitavanje definicija
$builder->addDefinitions(__DIR__ . '/config/dependencies.php');
$builder->addDefinitions(__DIR__ . '/config/services.php');
$builder->addDefinitions(__DIR__ . '/config/middlewares.php');
$builder->useAutowiring(true);

$container = $builder->build();

return $container;