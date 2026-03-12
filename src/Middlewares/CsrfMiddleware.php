<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Nyholm\Psr7\Response;
use App\Services\CsrfService;

class CsrfMiddleware implements MiddlewareInterface
{
    private CsrfService $csrfService;
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(CsrfService $csrfService , LoggerInterface $logger)
    {

        $this->logger = $logger;
        $this->csrfService = $csrfService;

        // Čitanje APP_DEBUG (kao u ErrorHandlerMiddleware)
        $debugEnv = getenv('APP_DEBUG') !== false
            ? getenv('APP_DEBUG')
            : ($_ENV['APP_DEBUG'] ?? '0');

        $this->debug = filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();

        // 1. Za GET zahteve – osiguraj token i stavi ga u request atribut
        if ($method === 'GET') {
            $request = $this->ensureCsrfToken($request);
        }

        // 2. Provera za mutirajuće metode
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $providedToken = $this->getProvidedCsrfToken($request);
            $storedToken   = $request->getCookieParams()['csrf_token'] ?? null;

            // Debug log – ukloni kasnije ako ne treba
            $this->logger->debug('CSRF provera', [
                'method'         => $method,
                'uri'            => $request->getUri()->getPath(),
                'provided_token' => $providedToken ?? '(nema)',
                'stored_token'   => $storedToken ?? '(nema)',
                'hash_equals'    => $providedToken && $storedToken ? hash_equals($providedToken, $storedToken) : false,
                'request_id'     => $request->getAttribute('request_id', 'unknown'),
            ]);

            if (!$providedToken || !$storedToken || !hash_equals($providedToken, $storedToken)) {
                return $this->createForbiddenResponse($request);
            }
        }

        // sve ok → prosledi dalje
        return $handler->handle($request);
    }

    /**
     * Osigurava da CSRF token postoji u cookie-ju i stavlja ga u request atribut
     */
    private function ensureCsrfToken(ServerRequestInterface $request): ServerRequestInterface
    {
        $cookies = $request->getCookieParams();

        if (!isset($cookies['csrf_token']) || empty($cookies['csrf_token'])) {
            // $csrfToken = bin2hex(random_bytes(32));
                $csrfToken = $this->csrfService->generateToken();
            setcookie('csrf_token', $csrfToken, [
                'expires'  => time() + 86400, // 24 sata – dovoljno za sesiju
                'path'     => '/',
                'secure'   => $request->getUri()->getScheme() === 'https',
                'httponly' => false,          // mora biti false da bi JS mogao čitati ako treba
                'samesite' => 'Strict'
            ]);

            $this->logger->debug('CSRF token generisan i postavljen u cookie', [
                'token'      => $csrfToken,
                'request_id' => $request->getAttribute('request_id', 'unknown')
            ]);

            // Stavi token u atribut da bi isti request (GET login) mogao da ga koristi u view-u
            $request = $request->withAttribute('csrf_token', $csrfToken);
        } else {
            // Token već postoji – samo ga prosledi u atribut
            $request = $request->withAttribute('csrf_token', $cookies['csrf_token']);

            $this->logger->debug('CSRF token već postoji u cookie-ju', [
                'token'      => $cookies['csrf_token'],
                'request_id' => $request->getAttribute('request_id', 'unknown')
            ]);
        }

        return $request;
    }

    private function getProvidedCsrfToken(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();

        // iz forme (POST)
        if (is_array($body) && !empty($body['_csrf_token'])) {
            return (string) $body['_csrf_token'];
        }

        // iz header-a (npr. AJAX / API)
        $header = $request->getHeaderLine('X-CSRF-Token');
        if ($header !== '') {
            return $header;
        }

        return null;
    }

    private function createForbiddenResponse(ServerRequestInterface $request): ResponseInterface
    {
        $payload = [
            'error'      => 'Nevalidan CSRF token',
            'request_id' => $request->getAttribute('request_id', 'n/a'),
        ];

        if ($this->debug) {
            $cookies = $request->getCookieParams();
            $body    = $request->getParsedBody();

            $payload['debug'] = [
                'provided_token'     => $this->getProvidedCsrfToken($request),
                'stored_token'       => $cookies['csrf_token'] ?? null,
                'parsed_body_keys'   => is_array($body) ? array_keys($body) : '(nije array)',
                'has_csrf_in_body'   => isset($body['_csrf_token']),
                'has_csrf_in_header' => $request->getHeaderLine('X-CSRF-Token') !== '',
                'request_method'     => $request->getMethod(),
                'uri'                => $request->getUri()->getPath(),
            ];
        }

        return new Response(
            403,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}