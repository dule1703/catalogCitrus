<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Nyholm\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class GuestMiddleware implements MiddlewareInterface
{
    private string $jwtSecret;
    private LoggerInterface $logger;

    public function __construct(string $jwtSecret, LoggerInterface $logger)
    {
        if (empty($jwtSecret)) {
            throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
        }
        $this->jwtSecret = $jwtSecret;
        $this->logger = $logger;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cookies = $request->getCookieParams();

        if (!isset($cookies['jwt_token'])) {
            return $handler->handle($request); // gost → nastavi
        }

        try {
            $token = $cookies['jwt_token'];
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            // Korisnik je prijavljen → redirect na dashboard
            return new Response(302, ['Location' => '/dashboard']);
        } catch (\Exception $e) {
            $this->logger->debug('GuestMiddleware: nevalidan JWT token', [
                'error' => $e->getMessage(),
                'request_id' => $request->getAttribute('request_id', 'unknown')
            ]);

            return $handler->handle($request); 
        }
    }
}