<?php
namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;
use App\Services\InputValidator;

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
        // Validacija obaveznih polja
        $requiredFields = ['username', 'email', 'password'];
        $validatedData = InputValidator::validateInputArray($data, $requiredFields);

        // Specifična validacija i sanitizacija
        $username = InputValidator::validateUsername($validatedData['username']);
        $email = InputValidator::validateEmail($validatedData['email']);
        $password = InputValidator::validatePassword($validatedData['password']);

        // Dodatna provera jačine lozinke
        if (!$this->isPasswordStrong($password)) {
            throw new InvalidArgumentException('Lozinka mora sadržati bar jedan specijalni znak (npr. !@#$%) i izbegavati uobičajene kombinacije.');
        }

        if ($this->userRepository->userExists($username, $email)) {
            throw new InvalidArgumentException('Korisničko ime ili email već postoji.');
        }

        $hashedPassword = $this->hashPassword($password);

        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
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
        // Validacija ulaznih podataka
        $username = InputValidator::validateUsername($username);
        $password = InputValidator::validatePassword($password);

        // 1. Preuzmi korisnika iz baze
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            $this->logFailedAttempt(null, $username);
            return null;
        }

        // 2. Proveri lozinku i rehash ako je potrebno
        if (!password_verify($password, $user['password'])) {
            $this->logFailedAttempt($user['id'] ?? null, $username);
            return null;
        }

        if ($this->needsRehash($user['password'])) {
            $this->updatePassword($user['id'], $password);
        }

        // 3. Loguj uspešan pokušaj
        $this->userRepository->logAttempt($user['id'], $this->getClientIp(), 1);

        // 4. Ukloni lozinku iz odgovora
        unset($user['password']);

        return $user;  
    }

    /**
     * Proverava jačinu lozinke.
     */
    private function isPasswordStrong(string $password): bool
    {
        // Provera specijalnih znakova
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return false;
        }
        // Provera protiv uobičajenih lozinki (jednostavna lista za sada)
        $commonPasswords = ['password123', '12345678', 'admin123'];
        return !in_array(strtolower($password), $commonPasswords, true);
    }

    /**
     * Hashuje lozinku sa prilagođenim parametrima.
     */
    private function hashPassword(string $password): string
    {
        $options = [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST * 2, // Povećano za veću sigurnost
            'time_cost' => 4, // Povećano za jači hash
            'threads' => 2, // Koristi više niti ako je dostupno
        ];
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }

    /**
     * Proverava da li hash treba rehashovati.
     */
    private function needsRehash(string $hashedPassword): bool
    {
        $options = [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST * 2,
            'time_cost' => 4,
            'threads' => 2,
        ];
        return password_needs_rehash($hashedPassword, PASSWORD_ARGON2ID, $options);
    }

    /**
     * Ažurira hash lozinke u bazi.
     */
    private function updatePassword(int $userId, string $password): void
    {
        $newHash = $this->hashPassword($password);
        $this->userRepository->updatePassword($userId, $newHash);
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