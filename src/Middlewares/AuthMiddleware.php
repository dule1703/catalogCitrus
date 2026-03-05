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

    private const RATE_LIMIT_MAX    = 5;
    private const RATE_LIMIT_WINDOW = 900;

    public function __construct(
        LoggerInterface $logger,
        JwtService $jwtService,
        RedisClient $redis,
        string $redirectUrl = '/login'
    ) {
        $this->logger     = $logger;
        $this->jwtService = $jwtService;
        $this->redis      = $redis;
        $this->redirectUrl = $redirectUrl;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $isJson    = $this->isJsonRequest($request);

        // Rate limiting
        if ($this->isRateLimited($ipAddress)) {
            $this->logger->warning("Rate limit premašen za IP: $ipAddress u AuthMiddleware");
            return $this->createUnauthorizedResponse($request, $isJson);
        }

        $cookies = $request->getCookieParams();
        $token   = $cookies['jwt_token'] ?? null;

        if ($token === null) {
            $this->logger->warning('JWT token nije prisutan: ' . $request->getUri()->getPath());
            return $this->createUnauthorizedResponse($request, $isJson);
        }

        try {
            if (!$this->jwtService->validate($token)) {
                $this->logger->warning('JWT token nije validan');
                $this->jwtService->clearAuthCookies(); // ← ovo mora da vrati Response sa set-cookie header-ima!
                return $this->createUnauthorizedResponse($request, $isJson);
            }

            if ($this->jwtService->isRevoked($token)) {
                $this->logger->warning('JWT token je opozvan');
                $this->jwtService->clearAuthCookies();
                return $this->createUnauthorizedResponse($request, $isJson);
            }

            // Sve OK → prosledi dalje u lancu
            return $handler->handle($request);

        } catch (\Exception $e) {
            $this->logger->error('Greška pri verifikaciji JWT: ' . $e->getMessage());
            return $this->createUnauthorizedResponse($request, $isJson);
        }
    }

    private function isRateLimited(string $ipAddress): bool
    {
        $key = "auth_rate_limit:$ipAddress";
        $attempts = (int) $this->redis->get($key) ?: 0;

        if ($attempts >= self::RATE_LIMIT_MAX) {
            return true;
        }

        // ★★★ OVO radi sa tvojom trenutnom RedisClient klasom ★★★
        $this->redis->set($key, $attempts + 1, self::RATE_LIMIT_WINDOW);

        return false;
    }
    private function isJsonRequest(ServerRequestInterface $request): bool
    {
        $accept     = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json');
    }

    private function createUnauthorizedResponse(
        ServerRequestInterface $request,
        bool $isJson
    ): ResponseInterface {
        if ($isJson) {
            return new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Neovlašćeni pristup'])
            );
        }

        // Za HTML → redirect
        return new Response(
            302,
            ['Location' => $this->redirectUrl]
        );
    }
}