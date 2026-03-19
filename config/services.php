<?php

use App\Services\UserService;
use App\Services\JwtService;
use App\Services\CsrfService;
use App\Services\EmailService;
use App\Services\CommentService;
use App\Services\InputValidator;
use App\Services\ProductService;
use App\Repositories\UserRepository;
use App\Repositories\CommentRepository;
use App\Repositories\ProductRepository;
use Psr\Log\LoggerInterface;
use App\RedisClient;



use function DI\autowire;
use function DI\get;

return [
    // JWT postavke
    'jwt.secret'        => fn() => $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.'),
    'jwt.issuer'        => fn() => $_ENV['APP_URL'] ?? 'citrus.ddwebapps.com',
    'jwt.accessExpiry'  => fn() => (int)($_ENV['JWT_ACCESS_EXPIRY'] ?? 900),
    'jwt.refreshExpiry' => fn() => (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000),

    // CSRF postavke
    'csrf.secret' => fn() => $_ENV['CSRF_SECRET'] ?? throw new \RuntimeException('CSRF_SECRET nije definisan u .env fajlu.'),

    // ✅ JwtService dobija UserRepository za upis tokena u jwt_tokens tabelu
    JwtService::class => autowire()
        ->constructorParameter('secret',         get('jwt.secret'))
        ->constructorParameter('issuer',         get('jwt.issuer'))
        ->constructorParameter('accessExpiry',   get('jwt.accessExpiry'))
        ->constructorParameter('refreshExpiry',  get('jwt.refreshExpiry'))
        ->constructorParameter('redis',          get(RedisClient::class))
        ->constructorParameter('userRepository', get(UserRepository::class)),

    UserService::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class)),

    ProductRepository::class => autowire(),
    ProductService::class => autowire(), 
   
    CsrfService::class => autowire(),

    InputValidator::class => autowire(),

    EmailService::class => autowire()
        ->constructorParameter('logger', get(LoggerInterface::class)),
];