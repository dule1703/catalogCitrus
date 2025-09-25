<?php
use FastRoute\RouteCollector;

return function (FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class, 
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class 
        ],
        'handler' => [\App\Controllers\UserController::class, 'showLoginForm']
    ]);

    $r->addRoute('POST', '/login', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class
        ],
        'handler' => [\App\Controllers\UserController::class, 'login']
    ]);
    
    $r->addRoute('GET', '/forgot-password', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class 
        ],
        'handler' => [\App\Controllers\UserController::class, 'showForgotPassword']
    ]);

    $r->addRoute('GET', '/register', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class 
        ],
        'handler' => [\App\Controllers\UserController::class, 'showRegisterForm']
    ]);
    
    $r->addRoute('POST', '/register', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\JsonInputMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class
        ],
        'handler' => [\App\Controllers\UserController::class, 'register']
    ]);    
    
    $r->addRoute('GET', '/register/success', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class 
        ],
        'handler' => [\App\Controllers\UserController::class, 'showSuccess']
    ]);
  
    $r->addRoute('GET', '/users', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\AuthMiddleware::class,
            \App\Middlewares\CsrfMiddleware::class 
        ],
        'handler' => [\App\Controllers\UserController::class, 'index']
    ]);

    $r->addRoute('POST', '/users', [
        'middleware' => [
            \App\Middlewares\ErrorHandlerMiddleware::class,
            \App\Middlewares\LoggingMiddleware::class,
            \App\Middlewares\AuthMiddleware::class,
            \App\Middlewares\JsonInputMiddleware::class
        ],
        'handler' => [\App\Controllers\UserController::class, 'store']
    ]);
};