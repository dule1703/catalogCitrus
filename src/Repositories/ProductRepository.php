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
}