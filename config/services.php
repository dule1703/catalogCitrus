<?php

use App\Services\UserService;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;

return [
    // JWT postavke kao zavisnosti
    'jwt.secret' => fn() => $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.'),
    'jwt.issuer' => fn() => $_ENV['APP_URL'] ?? 'yourapp.com',
    'jwt.expiry' => fn() => (int)($_ENV['JWT_EXPIRY'] ?? 3600),

     // JwtService — 
    JwtService::class => \DI\autowire()
        ->constructorParameter('secret', \DI\get('jwt.secret'))
        ->constructorParameter('issuer', \DI\get('jwt.issuer'))
        ->constructorParameter('expiry', \DI\get('jwt.expiry')),

    // UserService — autowiring će automatski injektovati zavisnosti
    UserService::class => \DI\autowire(),
];