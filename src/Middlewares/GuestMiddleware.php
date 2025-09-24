<?php
namespace App\Middlewares;

use Psr\Log\LoggerInterface;
use App\Interfaces\RequestMiddlewareInterface;

class GuestMiddleware 
{
    private string $jwtSecret;
    private LoggerInterface $logger;

    public function __construct(string $jwtSecret, LoggerInterface $logger)
    {
        if (empty($jwtSecret)) {
            throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
        }
        $this->jwtSecret = $jwtSecret;
        $this->logger = $logger;
    }

    /**
     * Bez parametara — kompatibilno sa tvojim index.php
     * Ako je korisnik prijavljen, preusmeri na /dashboard i prekini izvršavanje.
     */
    public function process(): ?array
    {
        if (!isset($_COOKIE['jwt_token'])) {
            return null; // Nastavi dalje
        }

        try {
            // Koristi isti JWT servis logiku kao u JwtService
            $token = $_COOKIE['jwt_token'];
            $decoded = \Firebase\JWT\JWT::decode(
                $token,
                new \Firebase\JWT\Key($this->jwtSecret, 'HS256')
            );

            // Ako je dekodiranje uspelo → korisnik je prijavljen
            header('Location: /dashboard');
            exit;

        } catch (\Exception $e) {
            // Token nije validan → tretiraj kao gosta
            $this->logger->debug('GuestMiddleware: nevalidan JWT token', [
                'error' => $e->getMessage(),
                'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
            ]);
            return null;
        }
    }
}