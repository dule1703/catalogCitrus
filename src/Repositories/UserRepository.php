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

    /**
     * Proverava da li korisnik postoji po username ili email-u.
     */
    public function userExists(string $username, string $email): bool
    {
        $result = $this->db->queryOne(
            'SELECT COUNT(*) as cnt FROM users WHERE username = :username OR email = :email',
            ['username' => $username, 'email' => $email]
        );

        return $result ? (int)$result['cnt'] > 0 : false;
    }

    /**
     * Kreira novog korisnika.
     * @param array{
     *     username: string,
     *     email: string,
     *     password: string,
     *     role: string,
     *     two_factor_enabled: int
     * } $data
     * @return int
     * @throws PDOException
     */
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

    /**
     * Čuva JWT token za korisnika.
     * @return bool true ako je uspešno sačuvano
     * @throws PDOException
     */
    public function saveJwtToken(int $userId, string $token, string $expiresAt): bool
    {
        $sql = 'INSERT INTO jwt_tokens (user_id, token, expires_at, created_at) 
                VALUES (:user_id, :token, :expiresAt, NOW())';
        $params = ['user_id' => $userId, 'token' => $token, 'expiresAt' => $expiresAt];

        return $this->db->execute($sql, $params) === 1;
    }

    /**
     * Loguje pokušaj prijave ili registracije.
     * @return bool true ako je uspešno zabeleženo
     * @throws PDOException
     */
    public function logAttempt(?int $userId, string $ip, int $success): bool
    {
        $sql = 'INSERT INTO login_attempts (user_id, ip_address, success, created_at) 
                VALUES (:user_id, :ip_address, :success, NOW())';
        $params = ['user_id' => $userId, 'ip_address' => $ip, 'success' => $success];

        return $this->db->execute($sql, $params) === 1;
    }
}