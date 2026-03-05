<?php
/**
 * Entry point aplikacije.
 * Sve HTTP zahteve obrađuje ovaj fajl.
 * Koristi FastRoute za rutiranje, PHP-DI za dependency injection, i middleware sistem.
 */
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// ✅ Učitavanje DI kontejnera i celog sistema (uključujući .env, konfiguraciju, itd.)
$container = require_once __DIR__ . '/../bootstrap.php';

try {
    // ✅ Generisanje jedinstvenog ID-a zahteva
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
    $_SERVER['REQUEST_ID'] = $requestId;

    

    $psr17Factory = new Psr17Factory();
    $creator = new ServerRequestCreator(
        $psr17Factory, // ServerRequestFactory
        $psr17Factory, // UriFactory
        $psr17Factory, // UploadedFileFactory
        $psr17Factory  // StreamFactory
    );
    $request = $creator->fromGlobals();                   

    // Dodaj request_id u atribut requesta (da svi middleware-i mogu da ga čitaju)
    $request = $request->withAttribute('request_id', $requestId);

    // Učitavanje ruta i dispatcher (ostaje isto)
    $routesFile = __DIR__ . '/../config/routes.php';
    if (!file_exists($routesFile)) {
        throw new \RuntimeException("Rute nisu pronađene: {$routesFile}");
    }
    $routeDefinitionCallback = require_once $routesFile;

    $dispatcher = FastRoute\cachedDispatcher($routeDefinitionCallback, [
        'cacheFile' => __DIR__ . '/../cache/route.cache',
        'cacheDisabled' => ($_ENV['APP_ENV'] ?? 'local') === 'local'
    ]);

    $httpMethod = $_SERVER['REQUEST_METHOD'];
    if ($httpMethod === 'HEAD') {
        $httpMethod = 'GET';
    }
    $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            // ... 404 odgovor (može ostati isto, ali bolje koristiti Response objekat kasnije)
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => '404 Not Found',
                'message' => 'Tražena ruta ne postoji.',
                'request_id' => $requestId
            ], JSON_UNESCAPED_UNICODE);
            break;

        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            // ... 405 odgovor (isto)
            $allowedMethods = $routeInfo[1];
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => '405 Method Not Allowed',
                'message' => 'Metoda nije dozvoljena za ovu rutu.',
                'allowed_methods' => $allowedMethods,
                'request_id' => $requestId
            ], JSON_UNESCAPED_UNICODE);
            break;

        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars    = $routeInfo[2];

            // Final handler – sada prima ServerRequestInterface i vraća ResponseInterface
            $finalHandler = new class($handler, $vars, $container) implements \Psr\Http\Server\RequestHandlerInterface {
                private $handler;
                private $vars;
                private $container;

                public function __construct($handler, $vars, $container) {
                    $this->handler = $handler;
                    $this->vars = $vars;
                    $this->container = $container;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                    [$controllerClass, $method] = $this->handler['handler'];
                    $controller = $this->container->get($controllerClass);

                    if (!method_exists($controller, $method)) {
                        throw new \RuntimeException("Metoda '{$method}' ne postoji u kontroleru '{$controllerClass}'.");
                    }

                    return $controller->{$method}($request, $this->vars);
                }
            };

            $response = executeMiddlewareStack($handler['middleware'] ?? [], $request, $finalHandler, $container);

            // Slanje odgovora (pretpostavljamo da je $response sada ResponseInterface)
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                header($name . ': ' . implode(', ', $values));
            }
            // Dodaj X-Request-ID ako želiš
            header('X-Request-ID: ' . $requestId);

            echo $response->getBody();
            break;
    }
} catch (\Throwable $e) {
    $errorHandler = $container->get(\App\Middlewares\ErrorHandlerMiddleware::class);
    $errorHandler->handleException($e);
}

/**
 * Nova verzija – radi samo sa PSR-7 tipovima
 */
function executeMiddlewareStack(
    array $middlewareClasses,
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Server\RequestHandlerInterface $finalHandler,
    $container
): \Psr\Http\Message\ResponseInterface {
    $handler = $finalHandler;

    foreach (array_reverse($middlewareClasses) as $middlewareClass) {
        $middleware = $container->get($middlewareClass);

        $next = $handler;

        $handler = new class($middleware, $next) implements \Psr\Http\Server\RequestHandlerInterface {
            private $middleware;
            private $next;

            public function __construct($middleware, \Psr\Http\Server\RequestHandlerInterface $next) {
                $this->middleware = $middleware;
                $this->next = $next;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                return $this->middleware->process($request, $this->next);
            }
        };
    }

    return $handler->handle($request);
}