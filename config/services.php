<?php
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Services\JwtService;

return [
    UserService::class => function($container) {
        return new UserService($container, $container->get(UserRepository::class));
    },
    JwtService::class => function($container) {
        if (!isset($_ENV['JWT_SECRET']) || empty($_ENV['JWT_SECRET'])) {
            throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
        }
        return new JwtService($_ENV['JWT_SECRET']);
    }
];