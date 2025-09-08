<?php
use App\Repositories\UserRepository;
use App\Services\UserService;

return [
    UserService::class => function($container) {
        return new UserService($container->get(UserRepository::class));
    }
];