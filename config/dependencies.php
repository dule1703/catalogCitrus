<?php

use App\Database;
use App\RedisClient;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\View\ViewRenderer;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    // Database konekcija
    Database::class => \DI\autowire()
        ->constructorParameter('host', $_ENV['DB_HOST'] ?? '127.0.0.1')
        ->constructorParameter('dbname', $_ENV['DB_NAME'] ?? 'testcitrus')
        ->constructorParameter('user', $_ENV['DB_USER'] ?? 'root')
        ->constructorParameter('pass', $_ENV['DB_PASS'] ?? ''),

    // Logger konfiguracija
    LoggerInterface::class => \DI\autowire(Logger::class)
        ->constructor('name', 'app') // Ime logger-a
        ->constructor('handlers', []) // Podrazumevani niz handler-a
        ->method('pushHandler', \DI\autowire(StreamHandler::class)
            ->constructor(__DIR__ . '/../../logs/app.log', Level::Debug)),

    // Redis klijent (ako se koristi)
    RedisClient::class => \DI\autowire()
        ->constructorParameter('host', $_ENV['REDIS_HOST'] ?? '127.0.0.1')
        ->constructorParameter('port', $_ENV['REDIS_PORT'] ?? 6379),

    // Repozitorijum
    UserRepository::class => \DI\autowire()
        ->constructorParameter('db', \DI\get(Database::class)),

    // Servis
    UserService::class => \DI\autowire()
        ->constructorParameter('container', \DI\get('Psr\Container\ContainerInterface'))
        ->constructorParameter('userRepository', \DI\get(UserRepository::class)),

    // ViewRenderer
    ViewRenderer::class => \DI\autowire()
        ->constructor(__DIR__ . '/../views'),
];