<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
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
            'iss' => 'yourapp.com',
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        return JWT::encode($payload, $this->secret, 'HS256');
    }
}