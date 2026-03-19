<?php

namespace App\Controllers;

use App\Services\CsrfService;
use App\Services\ProductService;
use App\View\ViewRenderer;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class ProductController
{
    private ProductService $productService;
    private CsrfService $csrfService;
    private ViewRenderer $viewRenderer;
    private LoggerInterface $logger;

    public function __construct(
        ProductService $productService,
        CsrfService $csrfService,
        ViewRenderer $viewRenderer,
        LoggerInterface $logger
    ) {
        $this->productService = $productService;
        $this->csrfService = $csrfService;
        $this->viewRenderer   = $viewRenderer;
        $this->logger         = $logger;
    }

    public function index(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("Product index called – učitavam 9 proizvoda");

        $products = $this->productService->getFeaturedProducts(9);
        $comments = $this->productService->getApprovedCommentsWithOffset(3, 0);

        $successMessage = null;
        $errorMessage   = null;

        $params = $request->getQueryParams();

        if (isset($params['success'])) {
            match ($params['success']) {
                'comment_added' => $successMessage = 'Hvala vam! Vaš komentar je uspešno poslat i biće objavljen nakon odobrenja.',
                default         => $successMessage = 'Operacija uspešno izvršena.'
            };
        }

        if (isset($params['error'])) {
            $errorMessage = urldecode($params['error']);
        }
        $content = $this->viewRenderer->render('products/index.php', [
            'products'   => $products ?? [],
            'comments'   => $comments ?? [],
            'title'      => 'Početna - CitrusApp',
            'csrfService' => $this->csrfService,               
            'csrf_token'  => $this->getCsrfToken($request) ?? '',     
            'successMessage'  => $successMessage,
            'errorMessage'    => $errorMessage,   
        ]);

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content
        );
    }

    private function getCsrfToken(ServerRequestInterface $request): string
    {
        return $request->getCookieParams()['csrf_token'] 
            ?? $request->getAttribute('csrf_token', '');
    }

    /**
     * AJAX endpoint - Učitaj još komentara
     */
    public function loadMoreComments(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $offset = (int)($request->getQueryParams()['offset'] ?? 0);

        $comments = $this->productService->getApprovedCommentsWithOffset(3, $offset);

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode([
                'comments' => $comments ?? [],
                'hasMore'  => count($comments ?? []) === 3
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    
}