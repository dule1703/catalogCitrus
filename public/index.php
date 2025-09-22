<?php

/**
 * Entry point aplikacije.
 * Sve HTTP zahteve obrađuje ovaj fajl.
 * Koristi FastRoute za rutiranje, PHP-DI za dependency injection, i middleware sistem.
 */

// ✅ Učitavanje DI kontejnera i celog sistema (uključujući .env, konfiguraciju, itd.)
$container = require_once __DIR__ . '/../bootstrap.php';

try {
    // ✅ Generisanje jedinstvenog ID-a zahteva (za logovanje i debug)
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
    $_SERVER['REQUEST_ID'] = $requestId; // Za middleware i logere

    // ✅ Učitavanje definicije ruta
    $routesFile = __DIR__ . '/../config/routes.php';
    if (!file_exists($routesFile)) {
        throw new \RuntimeException("Rute nisu pronađene: {$routesFile}");
    }

    $routeDefinitionCallback = require_once $routesFile;

    // ✅ Inicijalizacija FastRoute dispatchera sa keširanjem
    $dispatcher = FastRoute\cachedDispatcher($routeDefinitionCallback, [
        'cacheFile' => __DIR__ . '/../cache/route.cache',
        'cacheDisabled' => ($_ENV['APP_ENV'] ?? 'local') === 'local'
    ]);

    // ✅ Dobijanje HTTP metode i URI-ja
    $httpMethod = $_SERVER['REQUEST_METHOD'];

    // ✅ HEAD zahtevi koriste GET rutu (FastRoute preporuka)
    if ($httpMethod === 'HEAD') {
        $httpMethod = 'GET';
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // ✅ Uklanjanje query stringa
    if (($pos = strpos($uri, '?')) !== false) {
        $uri = substr($uri, 0, $pos);
    }

    // ✅ Dekodovanje URL-a i opciono uklanjanje trailing slash-a
    $uri = rawurldecode($uri);

    // ✅ Dispatch ruta
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case \FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => '404 Not Found',
                'message' => 'Tražena ruta ne postoji.',
                'request_id' => $requestId
            ], JSON_UNESCAPED_UNICODE);
            break;

        case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            header('Allow: ' . implode(', ', $allowedMethods));
            echo json_encode([
                'error' => '405 Method Not Allowed',
                'message' => 'Metoda nije dozvoljena za ovu rutu.',
                'allowed_methods' => $allowedMethods,
                'request_id' => $requestId
            ], JSON_UNESCAPED_UNICODE);
            break;

        case \FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1]; // ['handler' => [Controller::class, 'method'], 'middleware' => [...]]
            $vars = $routeInfo[2];    // Parametri iz ruta (npr. {id})

            // ✅ Kreiranje middleware lanca
            $middlewareStack = $handler['middleware'] ?? [];
            $finalHandler = function ($request) use ($handler, $vars, $container) {
                [$controllerClass, $method] = $handler['handler'];

                // ✅ Rezolucija kontrolera preko DI kontejnera
                $controller = $container->get($controllerClass);

                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException("Metoda '{$method}' ne postoji u kontroleru '{$controllerClass}'.");
                }

                // ✅ Izvršavanje metode kontrolera
                return $controller->{$method}($request, $vars);
            };

            // ✅ Izvršavanje middleware lanca
            $response = executeMiddlewareStack($middlewareStack, [], $finalHandler, $container);

            // ✅ Normalizacija odgovora (ako nije array, pretvori u standardni format)
            if (!is_array($response)) {
                $response = [
                    'status' => 200,
                    'headers' => [],
                    'body' => $response ?? ''
                ];
            }

            // ✅ Automatsko postavljanje JSON Content-Type ako je body niz
            if (is_array($response['body'] ?? null)) {
                if (empty($response['headers']['Content-Type'])) {
                    $response['headers']['Content-Type'] = 'application/json; charset=utf-8';
                }
                $response['body'] = json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // ✅ Postavljanje statusnog koda
            http_response_code($response['status'] ?? 200);

            // ✅ Dodavanje X-Request-ID u odgovor
            $response['headers']['X-Request-ID'] = $requestId;

            // ✅ Slanje HTTP headera
            foreach ($response['headers'] as $key => $value) {
                header("{$key}: {$value}");
            }

            // ✅ Slanje tela odgovora
            echo $response['body'] ?? '';
            break;
    }

} catch (\Throwable $e) {
    // ✅ Centralizovana obrada grešaka
    $errorHandler = $container->get(\App\Middlewares\ErrorHandlerMiddleware::class);
    $errorHandler->handleException($e);
}

/**
 * ✅ Izolovana logika za izvršavanje middleware lanca.
 * Podržava RequestMiddlewareInterface (sa $request) i obične middleware (bez parametara).
 */
function executeMiddlewareStack(array $middlewareClasses, $request, callable $finalHandler, $container)
{
    $next = $finalHandler;

    // ✅ Obrnuti redosled — poslednji middleware se izvršava prvi
    $middlewareClasses = array_reverse($middlewareClasses);

    foreach ($middlewareClasses as $middlewareClass) {
        $middleware = $container->get($middlewareClass);

        $current = $next;

        $next = function ($request) use ($middleware, $current) {
            // ✅ Provera tipa middleware-a
            if (method_exists($middleware, 'process')) {
                $result = $middleware->process($request, $current);

                // ✅ Ako middleware vrati array — to je konačan odgovor
                if (is_array($result)) {
                    return $result;
                }

                // ✅ Ako vrati null/false — nastavi dalje
                return $current($request);
            } else {
                // ✅ Fallback za middleware bez process() metode (npr. stariji format)
                $result = $middleware($request, $current);

                if (is_array($result)) {
                    return $result;
                }

                return $current($request);
            }
        };
    }

    return $next($request);
}