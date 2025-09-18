<?php
use App\Middlewares\GuestMiddleware;
use App\Middlewares\LoggingMiddleware;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\JsonInputMiddleware;
use App\Middlewares\ErrorHandlerMiddleware; 
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
    GuestMiddleware::class => function ($container) {
        return new GuestMiddleware($container);
    },
    JsonInputMiddleware::class => function ($container) {
        return new JsonInputMiddleware();
    },
    ErrorHandlerMiddleware::class => function ($container) { // Dodaj ovo
        return new ErrorHandlerMiddleware($container->get(LoggerInterface::class));
    },
];