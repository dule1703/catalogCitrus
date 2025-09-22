<?php
namespace App;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private PDO $conn;

    public function __construct(string $host, string $dbname, string $user, string $pass)
    {
        try {
            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname", $user, $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false //better security
                ]
            );
        } catch (PDOException $e) {
            throw new PDOException("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Returns the PDO connection instance.
     */
    public function getConnection(): PDO
    {
        return $this->conn;
    }

    /**
     * Prepares a SQL query.
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->conn->prepare($sql);
    }

    /**
     * Executes a prepared query with parameters and returns results.
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new PDOException("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Executes a query and returns the first row.
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new PDOException("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Returns the ID of the last inserted row.
     */
    public function lastInsertId(): string
    {
        try {
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            throw new PDOException("Failed to get last insert ID: " . $e->getMessage());
        }
    }
}