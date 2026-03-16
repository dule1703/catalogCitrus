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

    public function getApprovedComments(int $limit = 10): ?array {
        return $this->productRepository->getApprovedComments($limit);
    }

    public function getApprovedCommentsWithOffset(int $limit = 3, int $offset = 0): ?array {
        return $this->productRepository->getApprovedCommentsWithOffset($limit, $offset);
    }
}