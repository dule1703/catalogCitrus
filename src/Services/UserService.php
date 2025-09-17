<?php
namespace App\Services;

use App\Repositories\UserRepository;
use Exception;
use InvalidArgumentException;
use Firebase\JWT\JWT;
use Psr\Container\ContainerInterface;

/**
 * Service layer for user-related operations.
 */
class UserService
{
    private $userRepository;
    private $container;
    private $jwtSecret;
    private $jwtIssuer;
    private $jwtExpiry;

    /**
     * @param ContainerInterface $container DI container
     * @param UserRepository $userRepository Repository for user data
     */
    public function __construct(ContainerInterface $container, UserRepository $userRepository)
    {
        $this->container = $container;
        $this->userRepository = $userRepository;
        if (!isset($_ENV['JWT_SECRET']) || empty($_ENV['JWT_SECRET'])) {
            throw new \RuntimeException('JWT_SECRET nije definisan u .env fajlu.');
        }
        $this->jwtSecret = $_ENV['JWT_SECRET'];
        $this->jwtIssuer = $_ENV['APP_URL'] ?? 'yourapp.com';
        $this->jwtExpiry = (int)($_ENV['JWT_EXPIRY'] ?? 3600);
    }

    /**
     * Registers a new user, generates a JWT token, and sets it in a cookie.
     *
     * @param array $data User registration data (username, email, password)
     * @return bool Success status of registration
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If database or JWT operations fail
     */
    public function registerUser(array $data): bool
    {
        // Validate and sanitize input
        $username = trim($data['username'] ?? '');
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
            throw new InvalidArgumentException('Korisničko ime nije validno. Dozvoljeni su alfanumerički karakteri, donja crta i crtica, dužine 3-20 karaktera.');
        }

        $email = trim($data['email'] ?? '');
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Nevalidna email adresa.');
        }

        $password = $data['password'] ?? '';
        if (strlen($password) < 8 || !preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $password)) {
            throw new InvalidArgumentException('Lozinka mora imati najmanje 8 karaktera i sadržati slova i brojeve.');
        }

        // Check for duplicates
        if ($this->userRepository->userExists($username, $email)) {
            throw new InvalidArgumentException('Korisničko ime ili email već postoji.');
        }

        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => 'user',
            'two_factor_enabled' => 0
        ];

        try {
            // Create user in database
            $userId = $this->userRepository->create($userData);

            // Log successful registration attempt
            $ipAddress = $this->getClientIp();
            $this->userRepository->logAttempt($userId, $ipAddress, 1);

            return true;
        } catch (Exception $e) {
            $this->container->get(\Psr\Log\LoggerInterface::class)->error('Greška prilikom registracije: ' . $e->getMessage());
            throw new \RuntimeException('Došlo je do greške prilikom registracije.', 0, $e);
        }
    }

    /**
     * Generates a JWT token for the given user ID.
     *
     * @param int $userId User ID
     * @return string JWT token
     */
    private function generateJwt(int $userId): string
    {
        $payload = [
            'iss' => $this->jwtIssuer,
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $this->jwtExpiry
        ];
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Sets the JWT token in an HTTP-only secure cookie.
     *
     * @param string $jwt JWT token
     */
    private function setJwtCookie(string $jwt): void
    {
        setcookie('jwt_token', $jwt, [
            'expires' => time() + $this->jwtExpiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    /**
     * Retrieves the client IP address with fallback for proxies.
     *
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}