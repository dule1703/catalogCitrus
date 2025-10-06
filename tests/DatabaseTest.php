<?php

namespace Tests;

use App\Database;
use PDO;
use InvalidArgumentException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DatabaseTest extends TestCase
{
    private ?Database $db = null;  
    private ?PDO $pdoMock = null;

    protected function setUp(): void
    {
        // Kreiraj SQLite u memoriji za testove
        $this->pdoMock = new PDO('sqlite::memory:');
        $this->pdoMock->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Kreiraj test tabelu
        $this->pdoMock->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)");

        // Injektuj PDO u Database
        $this->db = new Database($this->pdoMock);
    }

    protected function tearDown(): void
    {
        $this->pdoMock = null;
        $this->db = null;
    }

    public function testConstructorWithInjectedPdo(): void
    {
        // Proveri da li se PDO uspešno injektuje
        $this->assertInstanceOf(PDO::class, $this->db->getConnection());
    }

    public function testConstructorWithInvalidDriver(): void
    {
        // Očekujemo InvalidArgumentException za nepoznati driver
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported database driver/');
        new Database(driver: 'oracle');  // Bez injektovanog PDO, baca grešku
    }

    public function testQueryWithParams(): void
    {
        // Ubaci test podatak
        $this->pdoMock->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        // Testiraj query sa parametrima
        $results = $this->db->query('SELECT * FROM users WHERE name = ?', ['John Doe']);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testQueryOne(): void
    {
        // Ubaci test podatak
        $this->pdoMock->exec("INSERT INTO users (name, email) VALUES ('Jane Doe', 'jane@example.com')");

        // Testiraj queryOne - treba da vrati samo prvi red
        $result = $this->db->queryOne('SELECT * FROM users WHERE name = ?', ['Jane Doe']);

        $this->assertIsArray($result);
        $this->assertEquals('Jane Doe', $result['name']);
    }

    public function testExecuteInsertAndLastInsertId(): void
    {
        // Testiraj INSERT
        $affectedRows = $this->db->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $this->assertEquals(1, $affectedRows);

        // Testiraj lastInsertId
        $lastId = $this->db->lastInsertId();
        $this->assertIsInt((int)$lastId);  // SQLite vraća string, ali je numeric
        $this->assertGreaterThan(0, (int)$lastId);

        // Proveri da li je ID sačuvan
        $result = $this->pdoMock->query("SELECT id FROM users WHERE name = 'Alice'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals((string)$lastId, $result['id']);
    }

    public function testTransactions(): void
    {
        // Počni transakciju
        $this->db->beginTransaction();

        // Unesi podatak
        $this->db->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Bob', 'bob@example.com']);

        // Rollback - podatak treba da nestane
        $this->db->rollback();

        $countAfterRollback = $this->db->query('SELECT COUNT(*) as count FROM users') [0]['count'] ?? 0;
        $this->assertEquals(0, $countAfterRollback);  // Nema podataka

        // Ponovo počni i commit
        $this->db->beginTransaction();
        $this->db->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Charlie', 'charlie@example.com']);
        $this->db->commit();

        $countAfterCommit = $this->db->query('SELECT COUNT(*) as count FROM users') [0]['count'] ?? 0;
        $this->assertEquals(1, $countAfterCommit);
    }

    public function testLoggerIsCalledOnQuery(): void
    {
        // Mock logger
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
                   ->method('debug')
                   ->with('Executing SQL: {sql}', $this->anything());  // Proveri da se pozove sa SQL-om

        $this->db->setLogger($loggerMock);

        // Izvrši query da se log pozove
        $this->db->query('SELECT * FROM users');
    }

    public function testPrepareStatement(): void
    {
        // Testiraj prepare - treba da vrati PDOStatement
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }
}