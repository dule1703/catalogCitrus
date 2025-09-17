<?php
namespace App\Controllers;

use Psr\Container\ContainerInterface;
use App\Services\UserService;
use App\View\ViewRenderer;
use Exception;
use InvalidArgumentException;

class UserController
{
    private $container;
    private $userService;
    private $viewRenderer;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->userService = $container->get(UserService::class);
        $this->viewRenderer = $container->get(ViewRenderer::class);
    }

    public function showLoginForm(): void
    {       
        $this->viewRenderer->render('user/login.php');
    }

    // Show registration form
    public function showRegisterForm(): void
    {
        $this->viewRenderer->render('user/create.php');
    }

    // Handle registration POST
    public function register(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $data = [
                'username' => $input['username'] ?? '',
                'email' => $input['email'] ?? '',
                'password' => $input['password'] ?? ''
            ];

            $this->userService->registerUser($data);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            $this->container->get(\Psr\Log\LoggerInterface::class)->error($e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Greška na serveru']);
            exit;
        }
    }

    // Handle login POST (placeholder - implementiraj logiku login-a)
    public function login(): void
    {
        // Placeholder - implementiraj autentifikaciju ovde (npr. provera username/password)
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';

            // Pretpostavka: UserService ima metodu za login
            $user = $this->userService->login($username, $password);

            if ($user) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Pogrešno korisničko ime ili lozinka']);
                exit;
            }
        } catch (Exception $e) {
            $this->container->get(\Psr\Log\LoggerInterface::class)->error($e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Greška na serveru']);
            exit;
        }
    }

    // Show success page
    public function showSuccess(): void
    {
        $this->viewRenderer->render('user/success.php');
    }
}