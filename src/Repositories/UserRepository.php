<?php
namespace App\Repositories;

use PDOException;
use App\Database;

class UserRepository
{
    private Database $db;

    public function __construct(Database $db) 
    {
        $this->db = $db;
    }

    // Check if user exists by username or email
    public function userExists(string $username, string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $username, 'email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    // Create new user
    public function create(array $data): int
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, email, password, role, two_factor_enabled, created_at) 
                VALUES (:username, :email, :password, :role, :two_factor_enabled, NOW())'
            );
            $stmt->execute($data);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new PDOException('Greška pri kreiranju korisnika: ' . $e->getMessage());
        }
    }

    // Save JWT token
    public function saveJwtToken(int $userId, string $token, string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO jwt_tokens (user_id, token, expires_at, created_at) 
            VALUES (:user_id, :token, :expires_at, NOW())'
        );
        $stmt->execute(['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);
    }

    // Log login/registration attempt
    public function logAttempt(?int $userId, string $ip, int $success): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (user_id, ip_address, success, created_at) 
            VALUES (:user_id, :ip_address, :success, NOW())'
        );
        $stmt->execute(['user_id' => $userId, 'ip_address' => $ip, 'success' => $success]);
    }
}