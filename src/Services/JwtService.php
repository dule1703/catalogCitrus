<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\RedisClient;
use RuntimeException;

class JwtService
{
    private string $secret;
    private string $issuer;
    private int $accessExpiry;
    private int $refreshExpiry;
    private RedisClient $redis;

    public function __construct(string $secret, string $issuer, int $accessExpiry, int $refreshExpiry, RedisClient $redis)
    {
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET nije definisan.');
        }
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->accessExpiry = $accessExpiry;  // npr. 15 min
        $this->refreshExpiry = $refreshExpiry;  // npr. 30 dana
        $this->redis = $redis;
    }

    /**
     * Generiše access i refresh token za korisnika
     */
    public function issueTokens(int $userId): array
    {
        $now = time();
        $jtiAccess = bin2hex(random_bytes(16));  // Jedinstveni identifikator za access token
        $jtiRefresh = bin2hex(random_bytes(16));  // Jedinstveni identifikator za refresh token

        // Access token payload
        $accessPayload = [
            'iss' => $this->issuer,
            'aud' => $this->issuer,  // Audience (možeš prilagoditi)
            'sub' => $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessExpiry,
            'jti' => $jtiAccess
        ];

        // Refresh token payload
        $refreshPayload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshExpiry,
            'jti' => $jtiRefresh,
            'type' => 'refresh'  // Obeležava da je refresh token
        ];

        $accessToken = JWT::encode($accessPayload, $this->secret, 'HS256');
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
            $key = strpos($token, '"type":"refresh"') !== false ? "jti:refresh:$jti" : "jti:access:$jti";
            return $this->redis->get($key) !== null;
        } catch (\Exception $e) {
            return true; // Ako dekodiranje ne uspe, pretpostavi da je opozvan
        }
    }

    /**
     * Postavlja access i refresh token u HTTP-only kolačiće
     */
    public function setAuthCookies(string $accessToken, string $refreshToken): void
    {
        $now = time();
        setcookie('access_token', $accessToken, [
            'expires' => $now + $this->accessExpiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        setcookie('refresh_token', $refreshToken, [
            'expires' => $now + $this->refreshExpiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Dobavlja vreme isteka access tokena
     */
    public function getAccessExpiry(): int
    {
        return $this->accessExpiry;
    }

    /**
     * Dobavlja vreme isteka refresh tokena
     */
    public function getRefreshExpiry(): int
    {
        return $this->refreshExpiry;
    }
}