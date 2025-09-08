<?php
namespace App;

use PDOException;

class Database {
    private $conn;

    public function __construct($host, $dbname, $user, $pass) {
        try {
            $this->conn = new \PDO(
                "mysql:host=$host;dbname=$dbname", $user, $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $e) {
            die("Connection failed:" . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try{
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $stmt->fetchAll();
        } catch(\PDOException $e) {
            die("Query failed:" . $e->getMessage());
        }
    }
}