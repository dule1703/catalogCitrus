<?php
namespace App\Middlewares;

use App\Interfaces\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;

class AuthMiddleware implements MiddlewareInterface
{
    private $logger;
    private $jwtService;
    private $redirectUrl;

    public function __construct(LoggerInterface $logger, JwtService $jwtService, string $redirectUrl = '/login')
    {
        $this->logger = $logger;
        $this->jwtService = $jwtService;
        $this->redirectUrl = $redirectUrl;
    }

    public function process()
    {
        $isJson = $this->isJsonRequest();

        if (!isset($_COOKIE['access_token'])) {
            $this->logger->warning('Access token nije prisutan: ' . ($_SERVER['REQUEST_URI'] ?? ''));
            return $this->createUnauthorizedResponse($isJson);
        }

        try {
            $token = $_COOKIE['access_token'];
            if (!$this->jwtService->validate($token)) {
                $this->logger->warning('Access token nije validan');
                return $this->createUnauthorizedResponse($isJson);
            }

            if ($this->jwtService->isRevoked($token)) {
                $this->logger->warning('Access token je opozvan');
                return $this->createUnauthorizedResponse($isJson);
            }

            return null; 
        } catch (\Exception $e) {
            $this->logger->error('Greška pri verifikaciji access tokena: ' . $e->getMessage());
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