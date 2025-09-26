<?php
namespace App\Middlewares;

use App\Interfaces\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;
use App\RedisClient;

class AuthMiddleware implements MiddlewareInterface
{
    private $logger;
    private $jwtService;
    private $redis;
    private $redirectUrl;
    private const RATE_LIMIT_MAX = 5; // Smanjeno na 5 za konzistentnost
    private const RATE_LIMIT_WINDOW = 900; // 15 minuta u sekundama

    public function __construct(LoggerInterface $logger, JwtService $jwtService, RedisClient $redis, string $redirectUrl = '/login')
    {
        $this->logger = $logger;
        $this->jwtService = $jwtService;
        $this->redis = $redis;
        $this->redirectUrl = $redirectUrl;
    }

    public function process()
    {
        $isJson = $this->isJsonRequest();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Provera rate limitinga
        if ($this->isRateLimited($ipAddress)) {
            $this->logger->warning("Rate limit premašen za IP: $ipAddress u AuthMiddleware");
            return $this->createUnauthorizedResponse($isJson);
        }

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

    private function isRateLimited(string $ipAddress): bool
    {
        $key = "auth_rate_limit:$ipAddress";
        $attempts = (int)$this->redis->get($key) ?: 0;

        if ($attempts >= self::RATE_LIMIT_MAX) {
            return true;
        }

        $this->redis->set($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
        return false;
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