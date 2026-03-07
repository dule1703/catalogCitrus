<?php

namespace App\Repositories;

use App\Database;

class UserRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->queryOne(
            'SELECT id, username, email, password, role, two_factor_enabled FROM users WHERE username = :username',
            ['username' => $username]
        );
    }

    public function findById(int $userId): ?array
    {
        return $this->db->queryOne(
            'SELECT id, username, email, role, two_factor_enabled FROM users WHERE id = :id',
            ['id' => $userId]
        );
    }

    public function userExists(string $username, string $email): bool
    {
        $result = $this->db->queryOne(
            'SELECT id FROM users WHERE username = :username OR email = :email',
            ['username' => $username, 'email' => $email]
        );
        return $result !== null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO users (username, email, password, role, two_factor_enabled, created_at)
             VALUES (:username, :email, :password, :role, :two_factor_enabled, NOW())',
            [
                'username'           => $data['username'],
                'email'              => $data['email'],
                'password'           => $data['password'],
                'role'               => $data['role'] ?? 'user',
                'two_factor_enabled' => 1, // Svaki novi korisnik mora proći 2FA pri prvom loginu
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $userId, string $newHash): bool
    {
        return $this->db->execute(
            'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id',
            ['password' => $newHash, 'id' => $userId]
        ) === 1;
    }

    /**
     * Postavlja two_factor_enabled = 0 nakon uspešne 2FA verifikacije.
     * Korisnik neće morati da prolazi 2FA sve dok se ne resetuje.
     */
    public function disableTwoFactorFlag(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET two_factor_enabled = 0, updated_at = NOW() WHERE id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Postavlja two_factor_enabled = 1 — korisnik mora proći 2FA pri sledećem loginu.
     * Poziva se pri logout-u ili po isteku perioda (scheduler).
     */
    public function enableTwoFactorFlag(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET two_factor_enabled = 1, updated_at = NOW() WHERE id = :id',
            ['id' => $userId]
        );
    }

    // ─────────────────────────────────────────
    //  Login attempts
    // ─────────────────────────────────────────

    public function logAttempt(int $userId, string $ipAddress, int $success): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (user_id, ip_address, success, created_at)
             VALUES (:user_id, :ip_address, :success, NOW())',
            [
                'user_id'    => $userId,
                'ip_address' => $ipAddress,
                'success'    => $success,
            ]
        );
    }

    // ─────────────────────────────────────────
    //  JWT tokeni
    // ─────────────────────────────────────────

    public function saveJwtToken(int $userId, string $token, string $expiresAt): void
    {
        $this->db->execute(
            'INSERT INTO jwt_tokens (user_id, token, created_at, expires_at)
             VALUES (:user_id, :token, NOW(), :expires_at)',
            [
                'user_id'    => $userId,
                'token'      => $token,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public function deleteExpiredTokens(int $userId): void
    {
        $this->db->execute(
            'DELETE FROM jwt_tokens WHERE user_id = :user_id AND expires_at < NOW()',
            ['user_id' => $userId]
        );
    }

    // ─────────────────────────────────────────
    //  2FA kodovi
    // ─────────────────────────────────────────

    public function saveTwoFactorCode(int $userId, string $code, string $expiresAt): void
    {
        $this->db->execute(
            'INSERT INTO two_factor_codes (user_id, code, expires_at, created_at)
             VALUES (:user_id, :code, :expires_at, NOW())',
            [
                'user_id'    => $userId,
                'code'       => $code,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public function getLatestTwoFactorCode(int $userId): ?array
    {
        return $this->db->queryOne(
            'SELECT id, code, expires_at
             FROM two_factor_codes
             WHERE user_id = :user_id AND expires_at > NOW()
             ORDER BY id DESC
             LIMIT 1',
            ['user_id' => $userId]
        );
    }

    public function deleteTwoFactorCodes(int $userId): void
    {
        $this->db->execute(
            'DELETE FROM two_factor_codes WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }
}