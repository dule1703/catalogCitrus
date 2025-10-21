<?php

use App\Middlewares\AuthMiddleware;
use App\Middlewares\LoggingMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\JsonInputMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use App\Middlewares\CsrfMiddleware;
use Psr\Log\LoggerInterface;

return [
    AuthMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class))
        // ->constructorParameter('jwtSecret', \DI\get('jwt.secret'))
        ->constructorParameter('redirectUrl', '/login'),

    LoggingMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
  
    GuestMiddleware::class => \DI\autowire()
        ->constructorParameter('jwtSecret', \DI\get('jwt.secret'))
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
  
    JsonInputMiddleware::class => \DI\autowire(),

    CsrfMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
  
    ErrorHandlerMiddleware::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
];