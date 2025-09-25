<?php

namespace App\Controllers;

use App\Services\UserService;
use App\View\ViewRenderer;
use App\Utilities\ApiResponse;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;
use App\Services\CsrfService;


class UserController
{
    private UserService $userService;
    private ViewRenderer $viewRenderer;
    private LoggerInterface $logger;
    private JwtService $jwtService;    
    private CsrfService $csrfService;
    

    public function __construct(
        UserService $userService,
        ViewRenderer $viewRenderer,
        LoggerInterface $logger,
        JwtService $jwtService,
        CsrfService $csrfService
    ) {
        $this->userService = $userService;
        $this->viewRenderer = $viewRenderer;
        $this->logger = $logger;
        $this->jwtService = $jwtService;   
        $this->csrfService = $csrfService; 
        $this->logger->info("UserController initialized successfully");
    }

    public function showLoginForm($request, $vars) {
        try {
            $this->logger->info("showLoginForm called");
            // ✅ CSRF token se kreira u CsrfMiddleware-u
            $content = $this->viewRenderer->render('user/login.php', [
                'csrfService' => $this->csrfService
            ]);
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showLoginForm failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading login form</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>'
            ];
        }
    }

    public function showRegisterForm($request, $vars) {
        try {
            $this->logger->info("showRegisterForm called");
            // ✅ CSRF token se kreira u CsrfMiddleware-u
            $content = $this->viewRenderer->render('user/create.php', [
                'csrfService' => $this->csrfService
            ]);
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showRegisterForm failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading register form</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>'
            ];
        }
    }
    public function showSuccess($request, $vars) {
        try {
            $this->logger->info("showSuccess called");
            $content = $this->viewRenderer->render('user/success.php');
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showSuccess failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading success page</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>'
            ];
        }
    }

    public function register($request, $vars) {
        try {
            $this->logger->info("register called");
            $input = $this->getRequestData();
            $this->logger->info("Input data received: " . json_encode(array_keys($input)));
            $result = $this->userService->registerUser($input);
            return ApiResponse::success(['message' => 'Korisnik uspešno registrovan']);
        } catch (\Throwable $e) {
            $this->logger->error("register failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => 'Greška pri registraciji: ' . $e->getMessage(),
                    'debug' => ($_ENV['APP_DEBUG'] ?? false) ? $e->getTraceAsString() : null
                ])
            ];
        }
    }

public function login($request, $vars)
{
    try {
        $this->logger->info("login called");
        $input = $this->getRequestData();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty credentials");
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Korisničko ime i lozinka su obavezni'])
            ];
        }

        $user = $this->userService->login($username, $password);
        if (!$user) {
            $this->logger->warning("Login failed for user: $username");
            return [
                'status' => 401,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Pogrešno korisničko ime ili lozinka'])
            ];
        }

        // ✅ Generiši JWT token
        $jwtToken = $this->jwtService->generate($user['id']);

        // ✅ Postavi kolačić
        setcookie('jwt_token', $jwtToken, [
            'expires' => time() + $this->jwtService->getExpiry(),
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        $this->logger->info("Login successful for user: $username");
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['success' => true])
        ];

    } catch (\Throwable $e) {
        $this->logger->error("login method failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => 'Greška na serveru: ' . $e->getMessage(),
                'debug' => ($_ENV['APP_DEBUG'] ?? false) ? $e->getTraceAsString() : null
            ])
        ];
    }
}

    /**
     * Helper method to get request data from different sources
     */
    private function getRequestData(): array
    {
        $input = [];
        
        // Try JSON input first
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input = $decoded;
                $this->logger->info("Got JSON input data");
            }
        }
        
        // Fallback to POST data
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
            $this->logger->info("Got POST data");
        }
        
        // Fallback to GET data for testing
        if (empty($input) && !empty($_GET)) {
            $input = $_GET;
            $this->logger->info("Got GET data");
        }
        
        
        return $input;
    }
}