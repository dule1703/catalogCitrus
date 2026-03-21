<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\RedisClient;
use App\Repositories\UserRepository;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class JwtService
{
    private string $secret;
    private string $issuer;
    private int $accessExpiry;
    private int $refreshExpiry;
    private RedisClient $redis;
    private UserRepository $userRepository;

    public function __construct(
        string $secret,
        string $issuer,
        int $accessExpiry,
        int $refreshExpiry,
        RedisClient $redis,
        UserRepository $userRepository
    ) {
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET nije definisan.');
        }
        $this->secret         = $secret;
        $this->issuer         = $issuer;
        $this->accessExpiry   = $accessExpiry;
        $this->refreshExpiry  = $refreshExpiry;
        $this->redis          = $redis;
        $this->userRepository = $userRepository;
    }

    /**
     * Generiše access i refresh token.
     * Upisuje JTI u Redis whitelist i token u jwt_tokens tabelu.
     */
    public function issueTokens(int $userId): array
    {
        $now        = time();
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
            'iss'  => $this->issuer,
            'sub'  => $userId,
            'iat'  => $now,
            'exp'  => $now + $this->refreshExpiry,
            'jti'  => $jtiRefresh,
            'type' => 'refresh'
        ];

        $accessToken  = JWT::encode($accessPayload, $this->secret, 'HS256');
        $refreshToken = JWT::encode($refreshPayload, $this->secret, 'HS256');

        // Whitelist — token važi dok JTI postoji u Redisu
        $this->redis->set("jti:access:{$jtiAccess}", '1', $this->accessExpiry);
        $this->redis->set("jti:refresh:{$jtiRefresh}", '1', $this->refreshExpiry);

        // Upis u bazu
        $expiresAt = date('Y-m-d H:i:s', $now + $this->accessExpiry);
        $this->userRepository->saveJwtToken($userId, $accessToken, $expiresAt);

        return [$accessToken, $refreshToken];
    }

    /**
     * Proverava potpis i istek tokena
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
     * Token je OPOZVAN ako JTI NE postoji u Redisu.
     * Whitelist logika:
     *   issueTokens() → dodaje JTI (token validan)
     *   revokeToken()  → briše JTI (token opozvan)
     *   isRevoked()    → true ako JTI ne postoji
     */
    public function isRevoked(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $jti     = $decoded->jti ?? null;

            if (!$jti) {
                return true;
            }

            $key = (isset($decoded->type) && $decoded->type === 'refresh')
                ? "jti:refresh:{$jti}"
                : "jti:access:{$jti}";

            // Opozvan ako NE postoji u Redisu
            return $this->redis->get($key) === null;

        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Opoziva token brisanjem JTI iz Redisa
     */
    public function revokeToken(string $token): void
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $jti     = $decoded->jti ?? null;
            if (!$jti) return;

            $key = (isset($decoded->type) && $decoded->type === 'refresh')
                ? "jti:refresh:{$jti}"
                : "jti:access:{$jti}";

            $this->redis->del($key);
        } catch (\Exception $e) {
            // Token nevalidan — nema šta da se opozove
        }
    }

    /**
     * Dekoduje token i vraća user id
     */
    public function getUserFromToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return ['id' => (int) $decoded->sub];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Dodaje JWT cookie-je u PSR-7 response.
     * secure=false na lokalnom HTTP, true na produkciji (HTTPS).
     */
    public function setAuthCookies(
        string $accessToken,
        string $refreshToken,
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $response = $response ?? new Response(200);
        $now      = time();
        $secure   = ($_ENV['APP_ENV'] ?? 'local') === 'production';

        $response = $response->withAddedHeader('Set-Cookie', $this->buildCookieString(
            'jwt_token', $accessToken, $now + $this->accessExpiry, $secure, true, 'Lax'
        ));
        $response = $response->withAddedHeader('Set-Cookie', $this->buildCookieString(
            'refresh_token', $refreshToken, $now + $this->refreshExpiry, $secure, true, 'Lax'
        ));

        return $response;
    }

    /**
     * Briše JWT cookie-je iz browsera
     */
    public function clearAuthCookies(?ResponseInterface $response = null): ResponseInterface
    {
        $response = $response ?? new Response(200);

        $response = $response->withAddedHeader('Set-Cookie', $this->buildCookieString(
            'jwt_token', '', time() - 3600, false, true, 'Lax'
        ));
        $response = $response->withAddedHeader('Set-Cookie', $this->buildCookieString(
            'refresh_token', '', time() - 3600, false, true, 'Lax'
        ));

        return $response;
    }

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
            $secure   ? 'secure'   : '',
            $httponly ? 'httponly' : '',
            'samesite=' . $samesite,
        ];
        return implode('; ', array_filter($parts));
    }


    public function getAccessExpiry(): int  { return $this->accessExpiry; }
    public function getRefreshExpiry(): int { return $this->refreshExpiry; }
}