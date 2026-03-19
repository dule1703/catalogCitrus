<?php

namespace App\Repositories;

use App\Database;

class CommentRepository {
    
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO comments(name, email, text, approved, created_at) 
                             VALUES(:name, :email, :text, 0, NOW())";

        return $this->db->execute($sql, [
            'name' => $data['name'],
            'email' => $data['email'],
            'text' => $data['text']
        ]) === 1;
    }
}