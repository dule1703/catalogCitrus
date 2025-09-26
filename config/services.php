<?php

use App\Services\UserService;
use App\Services\JwtService;
use App\Services\CsrfService; 
use Psr\Log\LoggerInterface;
use App\RedisClient;

return [
    // ✅ JWT postavke
    'jwt.secret' => fn() => $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.'),
    'jwt.issuer' => fn() => $_ENV['APP_URL'] ?? 'yourapp.com',
    'jwt.accessExpiry' => fn() => (int)($_ENV['JWT_ACCESS_EXPIRY'] ?? 900),  // 15 minuta
    'jwt.refreshExpiry' => fn() => (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000),  // 30 dana

    // ✅ CSRF postavke
    'csrf.secret' => fn() => $_ENV['CSRF_SECRET'] ?? throw new \RuntimeException('CSRF_SECRET nije definisan u .env fajlu.'),

    // ✅ JwtService — injektuje se u UserController::login()
    JwtService::class => \DI\autowire()
        ->constructorParameter('secret', \DI\get('jwt.secret'))
        ->constructorParameter('issuer', \DI\get('jwt.issuer'))
        ->constructorParameter('accessExpiry', \DI\get('jwt.accessExpiry'))
        ->constructorParameter('refreshExpiry', \DI\get('jwt.refreshExpiry'))
        ->constructorParameter('redis', \DI\get(RedisClient::class)),

    // ✅ UserService — uklanja JWT parametre, zadržava logger
    UserService::class => \DI\autowire()
        ->constructorParameter('logger', \DI\get(LoggerInterface::class)),

    // ✅ CsrfService — koristi se u UserController za generisanje tokena u view-ovima
    CsrfService::class => \DI\autowire(),
];