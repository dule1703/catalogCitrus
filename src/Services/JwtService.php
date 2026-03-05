<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\RedisClient;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response; // ili Laminas\Diactoros\Response, zavisno šta koristiš

class JwtService
{
    private string $secret;
    private string $issuer;
    private int $accessExpiry;
    private int $refreshExpiry;
    private RedisClient $redis;

    public function __construct(
        string $secret,
        string $issuer,
        int $accessExpiry,
        int $refreshExpiry,
        RedisClient $redis
    ) {
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET nije definisan.');
        }
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->accessExpiry = $accessExpiry;   // npr. 15 min
        $this->refreshExpiry = $refreshExpiry; // npr. 30 dana
        $this->redis = $redis;
    }

    /**
     * Generiše access i refresh token za korisnika
     */
    public function issueTokens(int $userId): array
    {
        $now = time();
        $jtiAccess  = bin2hex(random_bytes(16));
        $jtiRefresh = bin2hex(random_bytes(16));

        $accessPayload = [
            'iss' => $this->issuer,
            'aud' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessExpiry,
            'jti' => $jtiAccess
        ];

        $refreshPayload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshExpiry,
            'jti' => $jtiRefresh,
            'type' => 'refresh'
        ];

        $accessToken  = JWT::encode($accessPayload, $this->secret, 'HS256');
        $refreshToken = JWT::encode($refreshPayload, $this->secret, 'HS256');

        $this->redis->set("jti:access:$jtiAccess", 1, $this->accessExpiry);
        $this->redis->set("jti:refresh:$jtiRefresh", 1, $this->refreshExpiry);

        return [$accessToken, $refreshToken];
    }

    /**
     * Validira JWT token
     */
    public function validate(string $token): bool
    {
        try {
            JWT::decode($token, new Key($this->secret, 'HS256'));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Proverava da li je token opozvan na osnovu jti
     */
    public function isRevoked(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $jti = $decoded->jti;

            $key = (isset($decoded->type) && $decoded->type === 'refresh')
                ? "jti:refresh:$jti"
                : "jti:access:$jti";

            return $this->redis->get($key) !== null;
        } catch (\Exception $e) {
            return true; // Ako dekodiranje ne uspe, tretiraj kao opozvan
        }
    }

    /**
     * Dodaje access i refresh token kao Set-Cookie header-e u postojeći Response
     * Ako response nije prosleđen, kreira novi
     */
    public function setAuthCookies(
        string $accessToken,
        string $refreshToken,
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $response = $response ?? new Response(200);

        $now = time();

        // Access token cookie
        $accessCookie = $this->buildCookieString(
            'jwt_token',
            $accessToken,
            $now + $this->accessExpiry,
            true,  // secure
            true,  // httponly
            'Lax'
        );

        // Refresh token cookie
        $refreshCookie = $this->buildCookieString(
            'refresh_token',
            $refreshToken,
            $now + $this->refreshExpiry,
            true,
            true,
            'Lax'
        );

        $response = $response->withAddedHeader('Set-Cookie', $accessCookie);
        $response = $response->withAddedHeader('Set-Cookie', $refreshCookie);

        return $response;
    }

    /**
     * Briše auth cookie-je dodavanjem Set-Cookie sa prošlim expire-om
     */
    public function clearAuthCookies(?ResponseInterface $response = null): ResponseInterface
    {
        $response = $response ?? new Response(200);

        $clearAccess  = $this->buildCookieString('jwt_token', '', time() - 3600, true, true, 'Lax');
        $clearRefresh = $this->buildCookieString('refresh_token', '', time() - 3600, true, true, 'Lax');

        $response = $response->withAddedHeader('Set-Cookie', $clearAccess);
        $response = $response->withAddedHeader('Set-Cookie', $clearRefresh);

        return $response;
    }

    /**
     * Pomoćna metoda za generisanje Set-Cookie stringa
     */
    private function buildCookieString(
        string $name,
        string $value,
        int $expires,
        bool $secure,
        bool $httponly,
        string $samesite
    ): string {
        $parts = [
            urlencode($name) . '=' . urlencode($value),
            'expires=' . gmdate('D, d M Y H:i:s T', $expires),
            'path=/',
            $secure ? 'secure' : '',
            $httponly ? 'httponly' : '',
            'samesite=' . $samesite
        ];

        return implode('; ', array_filter($parts));
    }

    public function getAccessExpiry(): int
    {
        return $this->accessExpiry;
    }

    public function getRefreshExpiry(): int
    {
        return $this->refreshExpiry;
    }
}