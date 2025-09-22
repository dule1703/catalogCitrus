<?php
namespace App;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use RuntimeException;

require_once __DIR__ . '/vendor/autoload.php';

// Učitavanje .env fajla
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Provera .env varijabli
$requiredEnvVars = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    // 'DB_PASS',
    'REDIS_HOST',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_FROM_ADDRESS',
];

$missing = array_filter($requiredEnvVars, fn($var) => empty($_ENV[$var]));

if (!empty($missing)) {
    throw new RuntimeException('Nedostaju obavezne .env promenljive: ' . implode(', ', $missing));
}

// Kreiranje DI kontejnera
$builder = new ContainerBuilder();

if (($_ENV['APP_ENV'] ?? 'local') === 'production') {
    $diCacheDir = __DIR__ . '/cache/di';
    if (!is_dir($diCacheDir)) {
        mkdir($diCacheDir, 0755, true);
    }
    $builder->enableCompilation($diCacheDir);
}

// Učitavanje dependencies (Redis, Logger...), services, middlewares
$builder->addDefinitions(__DIR__ . '/config/dependencies.php');
$builder->addDefinitions(__DIR__ . '/config/services.php');
$builder->addDefinitions(__DIR__ . '/config/middlewares.php');
$builder->useAutowiring(true);
$container = $builder->build(); 


return $container;