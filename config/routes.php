<?php
use FastRoute\RouteCollector;

return function (RouteCollector $r) {
    $r->addRoute('GET', '/users', ['handler' => ['App\Controllers\UserController', 'index']]);
    $r->addRoute('POST', '/users', [
        'middleware' => [
            'App\Middleware\AuthMiddleware',
            'App\Middleware\LoggingMiddleware'
        ],
        'handler' => ['App\Controllers\UserController', 'store']
        
    ]);
};