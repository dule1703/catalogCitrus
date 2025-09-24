<?php
namespace App\Middlewares;

use App\Interfaces\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware implements MiddlewareInterface
{
    private $logger;
    private $jwtSecret;
    private $redirectUrl;

    public function __construct(LoggerInterface $logger, string $jwtSecret, string $redirectUrl = '/login')
    {
        $this->logger = $logger;
        $this->jwtSecret = $jwtSecret;
        $this->redirectUrl = $redirectUrl;
    }

    public function process()
    {
        $isJson = $this->isJsonRequest();

        if (!isset($_COOKIE['jwt_token'])) {
            $this->logger->warning('JWT token nije prisutan: ' . ($_SERVER['REQUEST_URI'] ?? ''));
            return $this->createUnauthorizedResponse($isJson);
        }

        try {
            $token = $_COOKIE['jwt_token'];
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            if ($decoded->exp < time()) {
                $this->logger->warning('JWT token je istekao');
                return $this->createUnauthorizedResponse($isJson);
            }

            return null; // OK
        } catch (\Exception $e) {
            $this->logger->error('Greška pri verifikaciji JWT tokena: ' . $e->getMessage());
            return $this->createUnauthorizedResponse($isJson);
        }
    }

    private function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json');
    }

    private function createUnauthorizedResponse(bool $isJson): array
    {
        if ($isJson) {
            return [
                'status' => 401,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Neovlašćeni pristup'])
            ];
        } else {
            return [
                'status' => 302,
                'headers' => ['Location' => $this->redirectUrl],
                'body' => ''
            ];
        }
    }
}