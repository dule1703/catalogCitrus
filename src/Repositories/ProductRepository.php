<?php

namespace App\Repositories;

use App\Database;

class ProductRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    public function getLimitProducts(int $limit = 9): ?array
    {
        return $this->db->query(
            'SELECT * FROM products ORDER BY id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }

    public function getApprovedComments(int $limit = 10): ?array {
        return $this->db->query(
            'SELECT id, name, email, text, created_at
             FROM comments
             WHERE approved = 1
             ORDER BY created_at DESC
             LIMIT :limit',
             ['limit' => $limit]
        );
    }

    public function getApprovedCommentsWithOffset(int $limit = 3, int $offset = 0): ?array {
        return $this->db->query(
            'SELECT id, name, text, email, created_at
             FROM comments 
             WHERE approved = 1
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset',
             [
                'limit' => $limit,
                'offset' => $offset
             ]
        );
    }
}