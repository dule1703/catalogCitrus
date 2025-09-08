<?php
namespace App;

use DI\Container;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/vendor/autoload.php';

// Učitavanje .env fajla
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Kreiranje DI kontejnera
$container = new Container();

// Definicija osnovnih zavisnosti
$container->set(Database::class, function () {
    return new Database(
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_NAME'] ?? 'testcitrus',
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? ''
    );
});

$container->set(RedisClient::class, function () {
    return new RedisClient(
        $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        $_ENV['REDIS_PORT'] ?? 6379
    );
});

$container->set(LoggerInterface::class, function () {
    $logger = new Logger('app');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/log/app.log', Logger::DEBUG));
    return $logger;
});

// Učitavanje repozitorijuma
$repositories = require __DIR__ . '/config/repositories.php';
foreach ($repositories as $key => $factory) {
    $container->set($key, $factory);
}

// Učitavanje servisa
$services = require __DIR__ . '/config/services.php';
foreach ($services as $key => $factory) {
    $container->set($key, $factory);
}

// Učitavanje middleware-a
$middlewares = require __DIR__ . '/config/middlewares.php';
foreach ($middlewares as $key => $factory) {
    $container->set($key, $factory);
}

// Error i exception handler-i
set_error_handler(function ($severity, $message, $file, $line) use ($container) {
    $container->get(LoggerInterface::class)->error("PHP Error: [$severity] $message in $file:$line");
});

set_exception_handler(function (\Throwable $exception) use ($container) {
    $container->get(LoggerInterface::class)->error(
        "Exception: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}"
    );
    if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
        throw $exception;
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Greška na serveru']);
});

return $container;