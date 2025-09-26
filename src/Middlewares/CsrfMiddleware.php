<?php
namespace App\Middlewares;

use App\Interfaces\RequestMiddlewareInterface;
use Psr\Log\LoggerInterface;

class CsrfMiddleware implements RequestMiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process($request, callable $next)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // ✅ Kreiraj CSRF token za GET zahteve ka formama
        if ($method === 'GET') {
            $this->ensureCsrfToken();
            
        }

        // ✅ Proveri CSRF token za POST/PUT/DELETE
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $providedToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            $storedToken = $_COOKIE['csrf_token'] ?? null;
// $this->logger->info('CSRF detalji', [
//             'providedToken' => $providedToken,
//             'storedToken' => $storedToken,
//             'headers' => getallheaders(),
//             'post_data' => $_POST,
//             'raw_input' => file_get_contents('php://input'),
//             'uri' => $_SERVER['REQUEST_URI'] ?? ''
//         ]);

            if (!$providedToken || !$storedToken || !hash_equals($providedToken, $storedToken)) {
                $this->logger->warning('CSRF validacija nije uspela', [
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
                ]);

                return [
                    'status' => 403,
                    'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                    'body' => [
                        'error' => 'Nevalidan CSRF token',
                        'request_id' => $_SERVER['REQUEST_ID'] ?? null
                    ]
                ];
            }
        }

        return $next($request);
    }

    private function ensureCsrfToken(): void
    {
        if (!isset($_COOKIE['csrf_token'])) {
            $csrfToken = bin2hex(random_bytes(32));
            setcookie('csrf_token', $csrfToken, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => false,
                'samesite' => 'Strict'
            ]);
            $_COOKIE['csrf_token'] = $csrfToken;
        }
    }
}