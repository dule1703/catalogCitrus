<?php
namespace App\Repositories;

use App\Database;
use InvalidArgumentException;

class UserRepository {
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function getAllUsers() {
        $result = $this->database->query("SELECT * FROM users");
        return $result;
    }

    public function getUserById($id) {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException('ID mora biti broj');
        }
        $result = $this->database->query("SELECT * FROM users WHERE id = :id", [':id' => $id]);
        return $result[0] ?? null;
    }
}