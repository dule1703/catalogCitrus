<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use FastRoute\Dispatcher;

$container = require_once __DIR__ . '/../bootstrap.php';

try {
    // ✅ Učitavanje ruta
    $routesFile = __DIR__ . '/../config/routes.php';
    if (!file_exists($routesFile)) {
        throw new \RuntimeException("Rute nisu pronađene");
    }

    $routeDefinitionCallback = require_once $routesFile;

    // ✅ Inicijalizacija FastRoute dispatchera sa keširanjem
    $dispatcher = FastRoute\cachedDispatcher($routeDefinitionCallback, [
        'cacheFile' => __DIR__ . '/../cache/route.cache',
        'cacheDisabled' => ($_ENV['APP_ENV'] ?? 'local') === 'local'
    ]);

    // ✅ Dispatch ruta
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case Dispatcher::NOT_FOUND:
            http_response_code(404);
            echo '<h1>404 - Stranica nije pronađena</h1>';
            break;
        case Dispatcher::METHOD_NOT_ALLOWED:
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Metoda nije dozvoljena']);
            break;
        case Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            // ✅ Kreiraj lanac middleware-a
            $middlewareStack = $handler['middleware'] ?? [];
            $finalHandler = function ($request) use ($handler, $vars, $container) {
                [$controllerClass, $method] = $handler['handler'];
                $controller = $container->get($controllerClass);
                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException("Metoda {$method} ne postoji u {$controllerClass}");
                }
                return $controller->{$method}($request, $vars);
            };

            $response = executeMiddlewareStack($middlewareStack, [], $finalHandler, $container);

            // ✅ Proveri da li je odgovor array
            if (!is_array($response)) {
                $response = [
                    'status' => 200,
                    'headers' => [],
                    'body' => $response ?? ''
                ];
            }

            // ✅ Pošalji odgovor
            http_response_code($response['status'] ?? 200);
            foreach ($response['headers'] ?? [] as $key => $value) {
                header("$key: $value");
            }
            echo $response['body'] ?? '';
            break;
    }
} catch (\Throwable $e) {
    // ✅ Centralizovana obrada grešaka
    $errorHandler = $container->get(\App\Middlewares\ErrorHandlerMiddleware::class);
    $errorHandler->handleException($e);
}

/**
 * ✅ Izolovana logika za izvršavanje middleware lanca
 */
function executeMiddlewareStack(array $middlewareClasses, $request, callable $finalHandler, $container)
{
    $next = $finalHandler;

    // ✅ Obrni redosled middleware-a
    $middlewareClasses = array_reverse($middlewareClasses);

    foreach ($middlewareClasses as $middlewareClass) {
        $middleware = $container->get($middlewareClass);
        $current = $next;
        $next = function ($request) use ($middleware, $current) {
            if ($middleware instanceof \App\Interfaces\RequestMiddlewareInterface) {
                $result = $middleware->process($request, $current);
                // ✅ Ako middleware vrati array — vrati odmah odgovor
                if (is_array($result)) {
                    return $result;
                }
                // ✅ Ako vrati null — nastavi dalje
                return $current($request);
            } else {
                // ✅ Za MiddlewareInterface — bez parametara
                $result = $middleware->process();
                if (is_array($result)) {
                    return $result;
                }
                return $current($request);
            }
        };
    }

    return $next($request);
}