<?php

namespace App\Controllers;

use Psr\Container\ContainerInterface;
use App\Services\UserService;
use App\View\ViewRenderer;
use Exception;
use App\Utilities\ApiResponse;
use Psr\Log\LoggerInterface;

class UserController
{
    private $container;
    private $userService;
    private $viewRenderer;
    private $logger;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        
        try {
            $this->userService = $container->get(UserService::class);
            $this->viewRenderer = $container->get(ViewRenderer::class);
            $this->logger->info("UserController initialized successfully");
        } catch (\Throwable $e) {
            $this->logger->error("Failed to initialize UserController dependencies: " . $e->getMessage());
            throw $e;
        }
    }

    // ✅ MODIFIKOVANO — vraća array, ne ispisuje odmah
    public function showLoginForm($request, $vars) {
        try {
            $this->logger->info("showLoginForm called");
            
            $content = $this->viewRenderer->render('user/login.php');
            
            $this->logger->info("showLoginForm completed successfully");
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showLoginForm failed: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading login form</h1><p>' . $e->getMessage() . '</p>'
            ];
        }
    }

    // ✅ MODIFIKOVANO — vraća array, ne ispisuje odmah
    public function showRegisterForm($request, $vars) {
        try {
            $this->logger->info("showRegisterForm called");
            
            $content = $this->viewRenderer->render('user/create.php');
            
            $this->logger->info("showRegisterForm completed successfully");
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showRegisterForm failed: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading register form</h1><p>' . $e->getMessage() . '</p>'
            ];
        }
    }

    // ✅ MODIFIKOVANO — vraća array, ne ispisuje odmah
    public function showSuccess($request, $vars) {
        try {
            $this->logger->info("showSuccess called");
            
            $content = $this->viewRenderer->render('user/success.php');
            
            $this->logger->info("showSuccess completed successfully");
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => $content
            ];
        } catch (\Throwable $e) {
            $this->logger->error("showSuccess failed: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html'],
                'body' => '<h1>Error loading success page</h1><p>' . $e->getMessage() . '</p>'
            ];
        }
    }

    // Handle registration POST
    public function register($request, $vars) {
        try {
            $this->logger->info("register called");
            
            // ✅ Properly get input data
            $input = $this->getRequestData();
            $this->logger->info("Input data received: " . json_encode(array_keys($input)));
            
            $result = $this->userService->registerUser($input);
            $this->logger->info("User registration successful");
            
            return ApiResponse::success(['message' => 'Korisnik uspešno registrovan']);
            
        } catch (\Throwable $e) {
            $this->logger->error("register failed: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => 'Greška pri registraciji: ' . $e->getMessage(),
                    'debug' => $_ENV['APP_DEBUG'] ?? false ? $e->getTraceAsString() : null
                ])
            ];
        }
    }
   
    public function login($request, $vars) {
        try {
            $this->logger->info("login called");
            
            $input = $this->getRequestData();
            $this->logger->info("Login attempt for user: " . ($input['username'] ?? 'unknown'));
            
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

            if ($user) {
                $this->logger->info("Login successful for user: $username");
                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode(['success' => true])
                ];
            } else {
                $this->logger->warning("Login failed for user: $username");
                return [
                    'status' => 401,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode(['error' => 'Pogrešno korisničko ime ili lozinka'])
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error("login method failed: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            
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
     * ✅ Helper method to get request data from different sources
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
        
        // Check global $GLOBALS['input'] as used in register method
        if (empty($input) && isset($GLOBALS['input']) && is_array($GLOBALS['input'])) {
            $input = $GLOBALS['input'];
            $this->logger->info("Got GLOBALS input data");
        }
        
        return $input;
    }
}