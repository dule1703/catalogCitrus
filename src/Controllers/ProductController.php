<?php

namespace App\Controllers;

use App\Services\CsrfService;
use App\Services\JwtService;
use App\Services\ProductService;
use App\Repositories\UserRepository;
use App\View\ViewRenderer;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ProductController
{
    private ProductService $productService;
    private CsrfService $csrfService;
    private UserRepository $userRepository;
    private ViewRenderer $viewRenderer;
    private LoggerInterface $logger;
    private JwtService $jwtService;         
    

    public function __construct(
        ProductService $productService,
        CsrfService $csrfService,
        UserRepository $userRepository,
        ViewRenderer $viewRenderer,
        LoggerInterface $logger,
        JwtService $jwtService       
        
    ) {
        $this->productService = $productService;
        $this->csrfService = $csrfService;
        $this->userRepository = $userRepository;
        $this->viewRenderer   = $viewRenderer;
        $this->logger         = $logger;
        $this->jwtService = $jwtService;       
    }

    public function index(ServerRequestInterface $request, array $vars): ResponseInterface {
        $products = $this->productService->getFeaturedProducts(9);
        $comments = $this->productService->getApprovedCommentsWithOffset(3, 0);

        // Poruke za komentar
        $successMessage = null;
        $errorMessage   = null;
        $queryParams = $request->getQueryParams();

        if (isset($queryParams['success']) && $queryParams['success'] === 'comment_added') {
            $successMessage = 'Hvala vam! Vaš komentar je uspešno poslat i biće objavljen nakon odobrenja.';
        }
        if (isset($queryParams['error']) && is_string($queryParams['error'])) {
            $errorMessage = urldecode($queryParams['error']);
        }

        
        $cookies = $request->getCookieParams();
        $token   = $cookies['jwt_token'] ?? null;
        
        // === PROVERA DA LI JE KORISNIK ULOGOVAN ===
        $isLoggedIn = false;
        $currentUser = null;
       
        if (!empty($token) && $this->jwtService->validate($token)) {
            $isLoggedIn = true;
            $currentUser = ['username' => 'Korisnik']; // privremeno
        }

        $content = $this->viewRenderer->render('products/index.php', [
            'products'       => $products ?? [],
            'comments'       => $comments ?? [],
            'title'          => 'Početna - CitrusApp',
            'csrfService'    => $this->csrfService,
            'csrf_token'     => $this->getCsrfToken($request),
            'successMessage' => $successMessage,
            'errorMessage'   => $errorMessage,
            'isLoggedIn'     => $isLoggedIn,
            'currentUser'    => $currentUser,
        ]);

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public function dashboard(ServerRequestInterface $request, array $vars): ResponseInterface {
        // Provera login statusa (isto kao u index metodi)
        $isLoggedIn = false;
        $currentUser = null;

        $cookies = $request->getCookieParams();
        $token = $cookies['jwt_token'] ?? null;

        if ($token) {
                $userData = $this->jwtService->getUserFromToken($token);
                if ($userData && !empty($userData['id'])) {
                    $isLoggedIn = true;
                    $currentUser = $this->userRepository->findById($userData['id']);
                }
            }

        $content = $this->viewRenderer->render('dashboard.php', [
            'isLoggedIn'  => $isLoggedIn,
            'currentUser' => $currentUser,
            'csrfService'  => $this->csrfService,     
            'csrf_token'   => $this->getCsrfToken($request),
            'title'       => 'Dashboard - CitrusApp',

        ]);

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
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