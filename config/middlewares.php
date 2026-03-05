<?php

use App\Middlewares\AuthMiddleware;
use App\Middlewares\LoggingMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\JsonInputMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use App\Middlewares\CsrfMiddleware;
use Psr\Log\LoggerInterface;

use function DI\autowire;
use function DI\get;

return [
    AuthMiddleware::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class))       
        ->constructorParameter('redirectUrl', '/login'),

    LoggingMiddleware::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class)),
  
    GuestMiddleware::class => autowire()
        ->constructorParameter('jwtSecret', get('jwt.secret'))
        ->constructorParameter('logger', get(LoggerInterface::class)),
  
    JsonInputMiddleware::class => autowire(),

    CsrfMiddleware::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class)),
  
    ErrorHandlerMiddleware::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class)),
];