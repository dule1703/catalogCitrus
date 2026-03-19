<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

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

    // Samo CsrfMiddleware — korisnik je u polu-autentifikovanom stanju
    // (kredencijali OK, JWT još nije izdat, čeka 2FA verifikaciju)
    $twoFa = [
        \App\Middlewares\CsrfMiddleware::class,
    ];

    // ─── Javne rute ───────────────────────────────────────────────────────

    $r->addRoute('GET', '/', [
        'middleware' => [\App\Middlewares\CsrfMiddleware::class,],
        'handler'    => [\App\Controllers\ProductController::class, 'index']
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

    // ─── Comments ─────────────────────────────────────────────────────────────

    $r->addRoute('GET', '/comments/load-more', [
        'middleware' => [],
        'handler'    => [\App\Controllers\ProductController::class, 'loadMoreComments']
    ]);

    $r->addRoute('POST', '/comments', [
    'middleware' => $guest,           // ili $guest ako dozvoljavaš anonimne komentare
    'handler'    => [\App\Controllers\CommentController::class, 'store']
]);

    // ─── 2FA ─────────────────────────────────────────────────────────────

    $r->addRoute('GET', '/verify-2fa', [
        'middleware' => $twoFa,
        'handler'    => [\App\Controllers\UserController::class, 'verifyTwoFactor']
    ]);

    $r->addRoute('POST', '/verify-2fa', [
        'middleware' => $twoFa,
        'handler'    => [\App\Controllers\UserController::class, 'verifyTwoFactor']
    ]);

    // ─── Zaštićene rute ───────────────────────────────────────────────────

    $r->addRoute('GET', '/home', [
        'middleware' => $auth,
        'handler'    => [\App\Controllers\UserController::class, 'showHome']
    ]);

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