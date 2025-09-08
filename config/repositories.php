<?php
use App\Repositories\UserRepository;
use App\Database;

return [
    UserRepository::class => function($container) {
        return new UserRepository($container->get(Database::class));
    }
];