<?php
namespace App\Services;

use App\Repositories\UserRepository;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;

class UserService
{
    private UserRepository $userRepository;
    private LoggerInterface $logger;

    public function __construct(
        UserRepository $userRepository,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    public function registerUser(array $data): bool
    {
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
            $userId = $this->userRepository->create($userData);
            $ipAddress = $this->getClientIp();
            $this->userRepository->logAttempt($userId, $ipAddress, 1);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Greška prilikom registracije: ' . $e->getMessage(), ['exception' => $e]);
            throw new RuntimeException('Došlo je do greške prilikom registracije.', 0, $e);
        }
    }

    /**
     * Pokušava prijavu korisnika.
     * 
     * @param string $username
     * @param string $password
     * @return array|null Vraća korisničke podatke i token ako je uspešno, inače null
     */
    public function login(string $username, string $password): ?array
    {
        // 1. Preuzmi korisnika iz baze
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            $this->logFailedAttempt(null, $username);
            return null;
        }

        // 2. Proveri lozinku
        if (!password_verify($password, $user['password'])) {
            $this->logFailedAttempt($user['id'] ?? null, $username);
            return null;
        }

        // 3. Loguj uspešan pokušaj
        $this->userRepository->logAttempt($user['id'], $this->getClientIp(), 1);

        // 4. Ukloni lozinku iz odgovora
        unset($user['password']);

        return $user;  // Trenutno vraća samo korisničke podatke (token će se dodati u kontroleru)
    }

    /**
     * Pomoćna metoda za logovanje neuspešnih pokušaja.
     */
    private function logFailedAttempt(?int $userId, string $username): void
    {
        $this->logger->warning("Failed login attempt", [
            'user_id' => $userId,
            'username' => $username,
            'ip' => $this->getClientIp()
        ]);
        $this->userRepository->logAttempt($userId, $this->getClientIp(), 0);
    }

    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}