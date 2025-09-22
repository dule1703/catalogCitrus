<?php

use App\Middlewares\AuthMiddleware;
use App\Middlewares\LoggingMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\JsonInputMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use Psr\Log\LoggerInterface;


if (!isset($_ENV['JWT_SECRET'])) {
    throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
}

return [
    
    AuthMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class))
        ->constructorParameter('jwtSecret', $_ENV['JWT_SECRET'])
        ->constructorParameter('loginPath', '/login'),
   
    LoggingMiddleware::class => \DI\autowire(),
  
    GuestMiddleware::class => \DI\autowire(),
  
    JsonInputMiddleware::class => \DI\autowire(),
  
    ErrorHandlerMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
];