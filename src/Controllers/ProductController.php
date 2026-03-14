<?php

namespace App\Controllers;

use App\Services\ProductService;
use App\View\ViewRenderer;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class ProductController
{
    private ProductService $productService;
    private ViewRenderer $viewRenderer;
    private LoggerInterface $logger;

    public function __construct(
        ProductService $productService,
        ViewRenderer $viewRenderer,
        LoggerInterface $logger
    ) {
        $this->productService = $productService;
        $this->viewRenderer   = $viewRenderer;
        $this->logger         = $logger;
    }

    public function index(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("Product index called – učitavam 9 proizvoda");

        $products = $this->productService->getFeaturedProducts(9);

        $content = $this->viewRenderer->render('products/index.php', [
            'products'   => $products ?? [],
            'title'      => 'Početna - CitrusApp',
            // // ako želiš da header zna da li je korisnik ulogovan (iz prethodnog odgovora)
            // 'isLoggedIn' => $request->getAttribute('isLoggedIn', false),
            // 'user'       => $request->getAttribute('user', null),
        ]);

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content
        );
    }
}