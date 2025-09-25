<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $issuer;
    private int $expiry;

    public function __construct(string $secret, string $issuer, int $expiry)
    {
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->expiry = $expiry;
    }

    public function validate(string $token): bool
    {
        try {
            JWT::decode($token, new Key($this->secret, 'HS256'));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function generate(int $userId): string
    {
        // Generiši CSRF token
        $csrfToken = bin2hex(random_bytes(32));
        
        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $this->expiry
        ];
        
        $jwtToken = JWT::encode($payload, $this->secret, 'HS256');
        
        // Postavi CSRF token u poseban kolačić (BEZ HttpOnly!)
        setcookie('csrf_token', $csrfToken, [
            'expires' => time() + $this->expiry,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => false, // ← Moramo da pristupimo iz JS-a
            'samesite' => 'Strict'
        ]);
        
        return $jwtToken;
    }

    public function getExpiry(): int
    {
        return $this->expiry;
    }
}