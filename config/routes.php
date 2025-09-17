<?php
use FastRoute\RouteCollector;

return function (FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'showLoginForm']
    ]);

    $r->addRoute('POST', '/login', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'login']
    ]);
    
    $r->addRoute('GET', '/forgot-password', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'showForgotPassword']
    ]);

    $r->addRoute('GET', '/register', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'showRegisterForm']
    ]);
    
    $r->addRoute('POST', '/register', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'register']
    ]);    
    
    $r->addRoute('GET', '/register/success', [
        'middleware' => [\App\Middlewares\LoggingMiddleware::class, \App\Middlewares\GuestMiddleware::class],
        'handler' => [\App\Controllers\UserController::class, 'showSuccess']
    ]);
  
    $r->addRoute('GET', '/users', ['handler' => [\App\Controllers\UserController::class, 'index']]);
    $r->addRoute('POST', '/users', [
        'middleware' => [
            'App\Middleware\AuthMiddleware',
            'App\Middleware\LoggingMiddleware'
        ],
        'handler' => [\App\Controllers\UserController::class, 'store']
    ]);
};