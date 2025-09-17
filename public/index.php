<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use FastRoute\Dispatcher;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../bootstrap.php';

// Učitavanje ruta
$routesFile = __DIR__ . '/../config/routes.php';
if (!file_exists($routesFile)) {
    $container->get(LoggerInterface::class)->error("Rute nisu pronađene: $routesFile");
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
        // header('Location: /');
        // exit;
        break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Metoda nije dozvoljena']);
        break;
    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        try {
            // Obrada middleware-a
            if (is_array($handler) && isset($handler['middleware'])) {
                foreach ($handler['middleware'] as $middleware) {
                    $middlewareInstance = $container->get($middleware);
                    $response = $middlewareInstance->process();
                    if ($response !== null) {
                        header('Content-Type: application/json');
                        echo is_array($response) ? json_encode($response) : $response;
                        exit;
                    }
                }
                $handler = $handler['handler'];
            }

            // Pretpostavka: $handler je [$controllerClass, $method]
            if (is_array($handler) && count($handler) === 2) {
                [$controllerClass, $method] = $handler;

                // Inicijalizacija kontrolera
                $controller = $container->get($controllerClass);

                // Pozivanje metode sa varijablama
                if (method_exists($controller, $method)) {
                    call_user_func_array([$controller, $method], [$vars]);
                } else {
                    throw new \RuntimeException("Metoda {$method} ne postoji u {$controllerClass}");
                }
            } else {
                throw new \RuntimeException("Nevažeći handler format");
            }
        } catch (\Throwable $e) {
            $container->get(LoggerInterface::class)->error(
                "Greška na serveru: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}"
            );
            http_response_code(500);
            header('Content-Type: application/json');
            //echo json_encode(['error' => 'Greška na serveru']);
            echo '<pre>';
echo "Greška: " . $e->getMessage() . "\n";
echo "Fajl: " . $e->getFile() . "\n";
echo "Linija: " . $e->getLine() . "\n";
//echo "Stack trace:\n" . $e->getTraceAsString();
echo '</pre>';
        }
        break;
}