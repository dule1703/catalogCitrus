<?php
namespace App;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use RuntimeException;

require_once __DIR__ . '/vendor/autoload.php';

// Učitavanje .env fajla
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ✅ Exception handler 
set_exception_handler(function (\Throwable $exception) use (&$container) {
    // Pokušaj da loguješ ako kontejner postoji
    if ($container && $container->has(LoggerInterface::class)) {
        $container->get(LoggerInterface::class)->error(
            "Exception: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}"
        );
    } else {
        // Fallback: loguj u fajl ako kontejner nije spreman
        $logFile = __DIR__ . '/logs/bootstrap.log';
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bootstrap Exception: " . $exception->getMessage() . "\n", FILE_APPEND);
    }

    // ✅ APP_DEBUG logika — uvek koristi $_ENV
    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($debug) {
        throw $exception; // developer vidi stack trace
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Greška na serveru']);
    exit(1);
});

// ✅ Provera .env varijabli — sada je bezbedno!
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

$builder->addDefinitions(__DIR__ . '/config/dependencies.php');
$builder->useAutowiring(true);
$container = $builder->build(); 

// Učitavanje repozitorijuma, servisa, middleware-a
$repositories = require __DIR__ . '/config/repositories.php';
foreach ($repositories as $key => $factory) {
    $container->set($key, $factory);
}

$services = require __DIR__ . '/config/services.php';
foreach ($services as $key => $factory) {
    $container->set($key, $factory);
}

$middlewares = require __DIR__ . '/config/middlewares.php';
foreach ($middlewares as $key => $factory) {
    $container->set($key, $factory);
}

// ✅ Shutdown handler 
register_shutdown_function(function () use ($container) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {

        if ($container && $container->has(LoggerInterface::class)) {
            $container->get(LoggerInterface::class)->critical(
                "Fatal Error: [{$error['type']}] {$error['message']} in {$error['file']}:{$error['line']}"
            );
        } else {
            $logFile = __DIR__ . '/logs/bootstrap.log';
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bootstrap Fatal: " . $error['message'] . "\n", FILE_APPEND);
        }

        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($debug) {
            echo "<h1>Fatal Error</h1><pre>";
            print_r($error);
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Greška na serveru']);
        exit(1);
    }
});

return $container;