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
        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $this->expiry
        ];
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    // Dodajemo getter za expiry — potreban za setcookie()
    public function getExpiry(): int
    {
        return $this->expiry;
    }
}