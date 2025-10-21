<?php
use App\Database;
use App\RedisClient;
use App\View\ViewRenderer;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

use function DI\autowire;
use function DI\get;

return [
    Database::class => autowire()
        ->constructorParameter('host',   $_ENV['DB_HOST'] ?? '127.0.0.1')
        ->constructorParameter('dbname', $_ENV['DB_NAME'] ?? 'testcitrus')
        ->constructorParameter('user',   $_ENV['DB_USER'] ?? 'root')
        ->constructorParameter('pass',   $_ENV['DB_PASS'] ?? ''),

    'jwt.secret' => $_ENV['JWT_SECRET'] ?? '',

    LoggerInterface::class => autowire(Logger::class)
        ->constructor('app')
        ->method('pushHandler', get(StreamHandler::class)),

    StreamHandler::class => autowire()
        ->constructor(__DIR__ . '/../logs/app.log', Level::Debug),

    RedisClient::class => autowire()
        ->constructorParameter('host', $_ENV['REDIS_HOST'] ?? '127.0.0.1')
        ->constructorParameter('port', (int)($_ENV['REDIS_PORT'] ?? 6379))
        ->constructorParameter('password', $_ENV['REDIS_PASS'] ?? ''),

    ViewRenderer::class => autowire()
        ->constructor(__DIR__ . '/../views'),

    'route.cache.file' => __DIR__ . '/../cache/route.cache',
];