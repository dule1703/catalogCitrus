<?php
use App\Middleware\AuthMiddleware;
use App\Middleware\LoggingMiddleware;
use Psr\Log\LoggerInterface;

return [
    AuthMiddleware::class => function ($container) {
        if (!isset($_ENV['JWT_SECRET'])) {
            throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
        }
        return new AuthMiddleware(
            $container->get(LoggerInterface::class),
            $_ENV['JWT_SECRET'],
            '/login'
        );
    },
    LoggingMiddleware::class => function ($container) {
        return new LoggingMiddleware($container->get(LoggerInterface::class));
    },
];