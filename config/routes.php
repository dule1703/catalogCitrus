<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // ─────────────────────────────────────────
    //  Middleware grupe
    //  ErrorHandlerMiddleware i LoggingMiddleware
    //  su globalni (dodaju se u index.php) i
    //  ne trebaju se navoditi ovde.
    // ─────────────────────────────────────────

    $guest = [
        \App\Middlewares\GuestMiddleware::class,
        \App\Middlewares\CsrfMiddleware::class,
    ];

    $guestJson = [
        \App\Middlewares\GuestMiddleware::class,
        \App\Middlewares\JsonInputMiddleware::class,
        \App\Middlewares\CsrfMiddleware::class,
    ];

    $auth = [
        \App\Middlewares\AuthMiddleware::class,
        \App\Middlewares\CsrfMiddleware::class,
    ];

    $authJson = [
        \App\Middlewares\AuthMiddleware::class,
        \App\Middlewares\JsonInputMiddleware::class,
    ];

    // ─────────────────────────────────────────
    //  Javne rute (guest)
    // ─────────────────────────────────────────

    $r->addRoute('GET', '/', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'showLoginForm']
    ]);

    $r->addRoute('GET', '/login', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'showLoginForm']
    ]);

    $r->addRoute('POST', '/login', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'login']
    ]);

    $r->addRoute('GET', '/register', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'showRegisterForm']
    ]);

    $r->addRoute('POST', '/register', [
        'middleware' => $guestJson,
        'handler'    => [\App\Controllers\UserController::class, 'register']
    ]);

    $r->addRoute('GET', '/register/success', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'showSuccess']
    ]);

    $r->addRoute('GET', '/forgot-password', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'showForgotPassword']
    ]);

    // ─────────────────────────────────────────
    //  2FA rute (guest – korisnik još nije ulogovan)
    // ─────────────────────────────────────────

    $r->addRoute('GET', '/verify-2fa', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'verifyTwoFactor']
    ]);

    $r->addRoute('POST', '/verify-2fa', [
        'middleware' => $guest,
        'handler'    => [\App\Controllers\UserController::class, 'verifyTwoFactor']
    ]);

    // ─────────────────────────────────────────
    //  Zaštićene rute (auth)
    // ─────────────────────────────────────────

    $r->addRoute('POST', '/logout', [
        'middleware' => $auth,
        'handler'    => [\App\Controllers\UserController::class, 'logout']
    ]);

    $r->addRoute('GET', '/users', [
        'middleware' => $auth,
        'handler'    => [\App\Controllers\UserController::class, 'index']
    ]);

    $r->addRoute('POST', '/users', [
        'middleware' => $authJson,
        'handler'    => [\App\Controllers\UserController::class, 'store']
    ]);
};