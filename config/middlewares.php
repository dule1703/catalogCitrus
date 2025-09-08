<?php
use App\Middleware\AuthMiddleware;
use App\Middleware\LoggingMiddleware;
use Psr\Log\LoggerInterface;

return [
    AuthMiddleware::class => function ($container) {
        return new AuthMiddleware(
            $container->get(LoggerInterface::class),
            $_ENV['JWT_SECRET'] ?? 'your-secret-key', 
            '/login'
        );
    },
    LoggingMiddleware::class => function ($container) {
        return new LoggingMiddleware($container->get(LoggerInterface::class));
    },
];