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

    public function userExists(string $username, string $email): bool
    {
        $result = $this->db->queryOne(
            'SELECT COUNT(*) as cnt FROM users WHERE username = :username OR email = :email',
            ['username' => $username, 'email' => $email]
        );
        return $result ? (int)$result['cnt'] > 0 : false;
    }

    public function create(array $data): int
    {
        $required = ['username', 'email', 'password', 'role', 'two_factor_enabled'];
        $missing = array_diff_key(array_flip($required), $data);
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', array_keys($missing)));
        }

        try {
            $sql = 'INSERT INTO users (username, email, password, role, two_factor_enabled, created_at)
                    VALUES (:username, :email, :password, :role, :two_factor_enabled, NOW())';
            $this->db->execute($sql, $data);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new PDOException('Greška pri kreiranju korisnika: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->queryOne(
            'SELECT id, username, email, password, role, two_factor_enabled FROM users WHERE username = :username',
            ['username' => $username]
        );
    }

    public function saveJwtToken(int $userId, string $token, string $expiresAt): bool
    {
        $sql = 'INSERT INTO jwt_tokens (user_id, token, expires_at, created_at)
                VALUES (:user_id, :token, :expiresAt, NOW())';
        $params = ['user_id' => $userId, 'token' => $token, 'expiresAt' => $expiresAt];
        return $this->db->execute($sql, $params) === 1;
    }

    public function logAttempt(?int $userId, string $ip, int $success): bool
    {
        $sql = 'INSERT INTO login_attempts (user_id, ip_address, success, created_at)
                VALUES (:user_id, :ip_address, :success, NOW())';
        $params = ['user_id' => $userId, 'ip_address' => $ip, 'success' => $success];
        return $this->db->execute($sql, $params) === 1;
    }

    public function updatePassword(int $userId, string $newHash): bool
    {
        $sql = 'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id';
        $params = ['password' => $newHash, 'id' => $userId];
        return $this->db->execute($sql, $params) === 1;
    }

    public function deleteTwoFactorCodes(int $userId): void
    {
        $sql = "DELETE FROM two_factor_codes WHERE user_id = :user_id";
        $this->db->execute($sql, ['user_id' => $userId]);
    }

    public function saveTwoFactorCode(int $userId, string $code, string $expiresAt): void
    {
        $sql = "INSERT INTO two_factor_codes (user_id, code, expires_at, created_at)
                VALUES (:user_id, :code, :expires_at, NOW())";
        $this->db->execute($sql, [
            'user_id' => $userId,
            'code' => $code,
            'expires_at' => $expiresAt
        ]);
    }

    public function getLatestTwoFactorCode(int $userId): ?array
    {
        $sql = "SELECT id, code, expires_at 
                FROM two_factor_codes 
                WHERE user_id = :user_id 
                AND expires_at > NOW()
                ORDER BY id DESC 
                LIMIT 1";
        return $this->db->queryOne($sql, ['user_id' => $userId]) ?: null;
    }

    public function markTwoFactorCodeAsUsed(int $codeId): void
    {
        $sql = "UPDATE two_factor_codes SET used = 1 WHERE id = :id";
        $this->db->execute($sql, ['id' => $codeId]);
    }
    
    public function findById(int $userId): ?array
    {
        return $this->db->queryOne(
            'SELECT id, username, email, role, two_factor_enabled FROM users WHERE id = :id',
            ['id' => $userId]
        );
    }
}