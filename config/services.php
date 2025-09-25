<?php

use App\Services\UserService;
use App\Services\JwtService;
use App\Services\CsrfService; 
use Psr\Log\LoggerInterface;

return [
    // ✅ JWT postavke
    'jwt.secret' => fn() => $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.'),
    'jwt.issuer' => fn() => $_ENV['APP_URL'] ?? 'yourapp.com',
    'jwt.expiry' => fn() => (int)($_ENV['JWT_EXPIRY'] ?? 3600),

    // ✅ CSRF postavke
    'csrf.secret' => fn() => $_ENV['CSRF_SECRET'] ?? throw new \RuntimeException('CSRF_SECRET nije definisan u .env fajlu.'),

    // ✅ JwtService — injektuje se u UserController::login()
    JwtService::class => \DI\autowire()
        ->constructorParameter('secret', \DI\get('jwt.secret'))
        ->constructorParameter('issuer', \DI\get('jwt.issuer'))
        ->constructorParameter('expiry', \DI\get('jwt.expiry')),

    // ✅ UserService — koristi JWT parametre i logger
    UserService::class => \DI\autowire()
        ->constructorParameter('jwtSecret', \DI\get('jwt.secret'))
        ->constructorParameter('jwtIssuer', \DI\get('jwt.issuer'))
        ->constructorParameter('jwtExpiry', \DI\get('jwt.expiry'))
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),

    // ✅ CsrfService — koristi se u UserController za generisanje tokena u view-ovima
    CsrfService::class => \DI\autowire(),
];