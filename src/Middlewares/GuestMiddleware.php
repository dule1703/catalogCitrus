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
        $this->logger    = $logger;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cookies = $request->getCookieParams();
        $token   = $cookies['jwt_token'] ?? null;

        // Nema cookie-ja — gost, nastavi normalno
        if (!$token) {
            return $handler->handle($request);
        }

        // Cookie postoji — proveri da li je token STVARNO validan
        try {
            JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            // Token je validan — korisnik je ulogovan, redirect na /home
            $this->logger->debug('GuestMiddleware: validan JWT, redirect na /home');
            return new Response(302, ['Location' => '/home']);
        } catch (\Exception $e) {
            // Token je nevalidan ili istekao — obrisi cookie i pusti kao gosta
            $this->logger->debug('GuestMiddleware: nevalidan/istekao JWT, nastavljam kao gost');
            $response = $handler->handle($request);
            // Obrisi neispravan cookie
            return $response->withAddedHeader(
                'Set-Cookie',
                'jwt_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; httponly'
            );
        }
    }
}