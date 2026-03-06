<?php

use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$container = require_once __DIR__ . '/../bootstrap.php';

// — Request
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
$_SERVER['REQUEST_ID'] = $requestId;

$factory = new Psr17Factory();
$request = (new ServerRequestCreator($factory, $factory, $factory, $factory))
    ->fromGlobals()
    ->withAttribute('request_id', $requestId);

// — Routing
$routesFile = __DIR__ . '/../config/routes.php';
if (!file_exists($routesFile)) {
    throw new \RuntimeException("Rute nisu pronađene: {$routesFile}");
}

$dispatcher = FastRoute\cachedDispatcher(require_once $routesFile, [
    'cacheFile'     => __DIR__ . '/../cache/route.cache',
    'cacheDisabled' => ($_ENV['APP_ENV'] ?? 'local') === 'local'
]);

// — Globalni middleware (uvek se izvršavaju)
$globalMiddleware = [
    \App\Middlewares\ErrorHandlerMiddleware::class,
    \App\Middlewares\LoggingMiddleware::class,
];

$httpMethod = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
$uri        = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$routeInfo  = $dispatcher->dispatch($httpMethod, $uri);


// — Dispatch
try {
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '404 Not Found', 'request_id' => $requestId], JSON_UNESCAPED_UNICODE);
            break;

        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowed = $routeInfo[1];
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowed));
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '405 Method Not Allowed', 'allowed_methods' => $allowed, 'request_id' => $requestId], JSON_UNESCAPED_UNICODE);
            break;

        case FastRoute\Dispatcher::FOUND:
            [$_, $handler, $vars] = $routeInfo;

            $finalHandler = new class($handler, $vars, $container) implements \Psr\Http\Server\RequestHandlerInterface {
                public function __construct(
                    private array $handler,
                    private array $vars,
                    private $container
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface {
                    [$controllerClass, $method] = $this->handler['handler'];
                    $controller = $this->container->get($controllerClass);

                    if (!method_exists($controller, $method)) {
                        throw new \RuntimeException("Metoda '{$method}' ne postoji u '{$controllerClass}'.");
                    }

                    return $controller->{$method}($request, $this->vars);
                }
            };

            $middleware = array_merge($globalMiddleware, $handler['middleware'] ?? []);
            $response   = executeMiddlewareStack($middleware, $request, $finalHandler, $container);

            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                header($name . ': ' . implode(', ', $values));
            }
            header('X-Request-ID: ' . $requestId);
            echo $response->getBody();
            break;
    }
} catch (\Throwable $e) {
    $container->get(\App\Middlewares\ErrorHandlerMiddleware::class)->handleException($e);
}

function executeMiddlewareStack(
    array $middlewareClasses,
    ServerRequestInterface $request,
    \Psr\Http\Server\RequestHandlerInterface $finalHandler,
    $container
): ResponseInterface {
    $handler = $finalHandler;

    foreach (array_reverse($middlewareClasses) as $middlewareClass) {
        $handler = new class($container->get($middlewareClass), $handler) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(
                private $middleware,
                private \Psr\Http\Server\RequestHandlerInterface $next
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface {
                return $this->middleware->process($request, $this->next);
            }
        };
    }

    return $handler->handle($request);
}