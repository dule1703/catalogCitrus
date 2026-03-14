<?php

namespace App\Services;

use App\Repositories\ProductRepository;

class ProductService {
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getFeaturedProducts(int $limit = 9): ?array {
        return $this->productRepository->getLimitProducts($limit);
    }
}