<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;
use App\RedisClient;
use Nyholm\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private JwtService $jwtService;
    private RedisClient $redis;
    private string $redirectUrl;

    // Rate limit samo za neuspešne pokušaje pristupa
    private const RATE_LIMIT_MAX    = 20;
    private const RATE_LIMIT_WINDOW = 900;

    public function __construct(
        LoggerInterface $logger,
        JwtService $jwtService,
        RedisClient $redis,
        string $redirectUrl = '/login'
    ) {
        $this->logger      = $logger;
        $this->jwtService  = $jwtService;
        $this->redis       = $redis;
        $this->redirectUrl = $redirectUrl;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $isJson    = $this->isJsonRequest($request);
        $cookies   = $request->getCookieParams();
        $token     = $cookies['jwt_token'] ?? null;

        if ($token === null) {
            $this->logger->warning('JWT token nije prisutan: ' . $request->getUri()->getPath());
            $this->incrementFailedAttempts($ipAddress);
            return $this->createUnauthorizedResponse($isJson);
        }

        try {
            if (!$this->jwtService->validate($token)) {
                $this->logger->warning('JWT token nije validan');
                $this->incrementFailedAttempts($ipAddress);
                return $this->jwtService->clearAuthCookies(
                    $this->createUnauthorizedResponse($isJson)
                );
            }

            if ($this->jwtService->isRevoked($token)) {
                $this->logger->warning('JWT token je opozvan');
                $this->incrementFailedAttempts($ipAddress);
                return $this->jwtService->clearAuthCookies(
                    $this->createUnauthorizedResponse($isJson)
                );
            }

            // Sve OK — resetuj brojač i nastavi
            $this->resetFailedAttempts($ipAddress);
            return $handler->handle($request);

        } catch (\Exception $e) {
            $this->logger->error('Greška pri verifikaciji JWT: ' . $e->getMessage());
            $this->incrementFailedAttempts($ipAddress);
            return $this->createUnauthorizedResponse($isJson);
        }
    }

    private function incrementFailedAttempts(string $ip): void
    {
        $key      = "auth_fail:{$ip}";
        $attempts = (int)($this->redis->get($key) ?? 0) + 1;
        $this->redis->set($key, $attempts, self::RATE_LIMIT_WINDOW);
    }

    private function resetFailedAttempts(string $ip): void
    {
        $this->redis->del("auth_fail:{$ip}");
    }

    private function isJsonRequest(ServerRequestInterface $request): bool
    {
        $accept      = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');
        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json');
    }

    private function createUnauthorizedResponse(bool $isJson): ResponseInterface
    {
        if ($isJson) {
            return new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Neovlašćeni pristup'])
            );
        }
        return new Response(302, ['Location' => $this->redirectUrl]);
    }
}