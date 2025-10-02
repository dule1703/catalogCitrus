<?php

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Database
{
    private PDO $conn;
    private ?LoggerInterface $logger = null;

    /**
     * Konstruktor — prima PDO instancu (injektovanu preko DI) ili kreira novu ako nije data.
     */
    public function __construct(
        ?PDO $conn = null,
        string $driver = 'mysql',
        string $host = '',
        string $dbname = '',
        string $user = '',
        string $pass = '',
        string $charset = 'utf8mb4'
    ) {
        if ($conn) {
            $this->conn = $conn;
            return;
        }

        try {
            $dsn = match ($driver) {
                'mysql' => "mysql:host=$host;dbname=$dbname;charset=$charset",
                'pgsql' => "pgsql:host=$host;dbname=$dbname",
                'sqlite' => "sqlite:" . ($dbname ?: ':memory:'),
                default => throw new InvalidArgumentException("Unsupported database driver: $driver")
            };

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false, //integer/boolean kolone
            ];

        
            $this->conn = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Postavlja logger.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Vraća PDO konekciju
     */
    public function getConnection(): PDO
    {
        return $this->conn;
    }

    /**
     * Priprema SQL upit
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->conn->prepare($sql);
    }

    /**
     * Izvršava SELECT upit i vraća sve redove.
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->executeQuery($sql, $params, 'fetchAll');
    }

    /**
     * Izvršava SELECT upit i vraća prvi red.
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        return $this->executeQuery($sql, $params, 'fetch');
    }

    /**
     * Izvršava INSERT/UPDATE/DELETE i vraća broj zahvaćenih redova.
     */
    public function execute(string $sql, array $params = []): int
    {
        $this->logQuery($sql, $params);

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException("Query execution failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Vraća ID poslednjeg unetog reda.
     * @return int|string
     */
    public function lastInsertId(): int|string
    {
        return $this->conn->lastInsertId();
    }

    /**
     * Pokreće transakciju.
     */
    public function beginTransaction(): void
    {
        $this->conn->beginTransaction();
    }

    /**
     * Potvrđuje transakciju.
     */
    public function commit(): void
    {
        $this->conn->commit();
    }

    /**
     * Poništava transakciju.
     */
    public function rollback(): void
    {
        $this->conn->rollback();
    }

    /**
     * Interna metoda za izvršavanje SELECT upita — DRY.
     */
    private function executeQuery(string $sql, array $params, string $fetchMethod): mixed
    {
        $this->logQuery($sql, $params);

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->{$fetchMethod}();
        } catch (PDOException $e) {
            throw new PDOException("Query failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Loguje upit ako je logger podešen.
     */
    private function logQuery(string $sql, array $params): void
    {
        if ($this->logger !== null) {
            $this->logger->debug("Executing SQL: {sql}", [
                'sql' => $sql,
                'params' => $params
            ]);
        }
    }
}