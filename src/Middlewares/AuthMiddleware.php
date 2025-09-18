<?php
namespace App\Middlewares;

use App\Interfaces\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware implements MiddlewareInterface {
    private $logger;
    private $jwtSecret;
    private $redirectUrl;

    public function __construct(LoggerInterface $logger, string $jwtSecret, string $redirectUrl = '/login') {
        $this->logger = $logger;
        $this->jwtSecret = $jwtSecret;
        $this->redirectUrl = $redirectUrl;
    }

    public function process() {
        // Provera prisustva JWT tokena u cookie-ju
        if (!isset($_COOKIE['jwt_token'])) {
            $this->logger->warning('JWT token nije prisutan: ' . $_SERVER['REQUEST_URI']);
            header("Location: $this->redirectUrl");
            $isApiRequest = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            return $isApiRequest
                ? ['error' => 'Neovlašćeni pristup', 'redirect' => $this->redirectUrl]
                : "Redirekcija na $this->redirectUrl";
        }

        try {
            // Dekodiranje JWT tokena
            $token = $_COOKIE['jwt_token'];
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Provera validnosti tokena (npr. isteknuće)
            if ($decoded->exp < time()) {
                $this->logger->warning('JWT token je istekao: ' . $_SERVER['REQUEST_URI']);
                header("Location: $this->redirectUrl");
                return ['error' => 'JWT token je istekao', 'redirect' => $this->redirectUrl];
            }

            // Token je validan, nastavi dalje
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Greška pri verifikaciji JWT tokena: ' . $e->getMessage());
            header("Location: $this->redirectUrl");
            $isApiRequest = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            return $isApiRequest
                ? ['error' => 'Nevalidan JWT token', 'redirect' => $this->redirectUrl]
                : "Redirekcija na $this->redirectUrl";
        }
    }
}