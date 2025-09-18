<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use FastRoute\Dispatcher;

$container = require_once __DIR__ . '/../bootstrap.php'; // Uhvati povratnu vrednost iz bootstrap.php

// Učitavanje ruta
$routesFile = __DIR__ . '/../config/routes.php';
if (!file_exists($routesFile)) {
    throw new \RuntimeException("Rute nisu pronađene");
}

$routeDefinitionCallback = require_once $routesFile;

// Inicijalizacija FastRoute dispatchera sa keširanjem
$dispatcher = FastRoute\cachedDispatcher($routeDefinitionCallback, [
    'cacheFile' => __DIR__ . '/../cache/route.cache',
    'cacheDisabled' => ($_ENV['APP_ENV'] ?? 'local') === 'local'
]);

// Dobijanje HTTP metoda i URI-ja
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

        // Inicijalizuj osnovni request
        $request = [];

        // Kreiraj lanac middleware-a
        if (is_array($handler) && isset($handler['middleware'])) {
            $middlewareStack = array_reverse($handler['middleware']); // Obrni za pravilni redosled
            $next = function ($request) use ($handler, $vars, $container) {
                if (is_array($handler) && isset($handler['handler']) && is_array($handler['handler']) && count($handler['handler']) === 2) {
                    [$controllerClass, $method] = $handler['handler'];
                    $controller = $container->get($controllerClass);
                    if (method_exists($controller, $method)) {
                        $result = call_user_func_array([$controller, $method], [$request, $vars]);
                        return is_array($result) ? $result : ['body' => $result];
                    } else {
                        throw new \RuntimeException("Metoda {$method} ne postoji u {$controllerClass}");
                    }
                } else {
                    throw new \RuntimeException("Nevažeći handler format");
                }
            };

            foreach ($middlewareStack as $middlewareClass) {
                $middleware = $container->get($middlewareClass);
                if ($middleware instanceof \App\Interfaces\RequestMiddlewareInterface) {
                    $current = $next;
                    $next = function ($request) use ($middleware, $current) {
                        return $middleware->process($request, $current);
                    };
                } else {
                    $current = $next;
                    $next = function ($request) use ($middleware, $current) {
                        $middleware->process();
                        return $current($request);
                    };
                }
            }

            $response = $next($request);

            // Obrada odgovora
            if (is_array($response)) {
                http_response_code($response['status'] ?? 200);
                foreach ($response['headers'] ?? [] as $key => $value) {
                    header("$key: $value");
                }
                echo $response['body'] ?? '';
            }
        } else {
            throw new \RuntimeException("Nevažeći handler format");
        }
        break;
}