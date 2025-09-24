<?php

use App\Services\UserService;
use App\Services\JwtService;
use Psr\Log\LoggerInterface;

return [
    'jwt.secret' => fn() => $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.'),
    'jwt.issuer' => fn() => $_ENV['APP_URL'] ?? 'yourapp.com',
    'jwt.expiry' => fn() => (int)($_ENV['JWT_EXPIRY'] ?? 3600),

    JwtService::class => \DI\autowire()
        ->constructorParameter('secret', \DI\get('jwt.secret'))
        ->constructorParameter('issuer', \DI\get('jwt.issuer'))
        ->constructorParameter('expiry', \DI\get('jwt.expiry')),

    UserService::class => \DI\autowire()
        ->constructorParameter('jwtSecret', \DI\get('jwt.secret'))
        ->constructorParameter('jwtIssuer', \DI\get('jwt.issuer'))
        ->constructorParameter('jwtExpiry', \DI\get('jwt.expiry'))
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),
];