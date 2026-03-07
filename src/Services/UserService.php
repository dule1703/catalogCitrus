<?php

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;
use App\RedisClient;

class UserService
{
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private RedisClient $redis;
    private EmailService $emailService;

    private const MAX_ATTEMPTS          = 5;
    private const BLOCK_DURATION        = 3600; // 1 sat
    private const RATE_LIMIT_WINDOW     = 900;  // 15 min
    private const RATE_LIMIT_MAX        = 5;
    private const REGISTER_RATE_LIMIT_MAX = 10;

    public function __construct(
        UserRepository $userRepository,
        LoggerInterface $logger,
        RedisClient $redis,
        EmailService $emailService
    ) {
        $this->userRepository = $userRepository;
        $this->logger         = $logger;
        $this->redis          = $redis;
        $this->emailService   = $emailService;
    }

    public function registerUser(array $data): bool
    {
        $ipAddress = $this->getClientIp();

        if ($this->isRateLimitedForRegister($ipAddress)) {
            $this->logger->warning("Rate limit premašen za IP: $ipAddress prilikom registracije");
            throw new RuntimeException('Previše pokušaja registracije. Pokušaj ponovo kasnije.');
        }

        $requiredFields = ['username', 'email', 'password'];
        $validatedData  = InputValidator::validateInputArray($data, $requiredFields);
        $username       = InputValidator::validateUsername($validatedData['username']);
        $email          = InputValidator::validateEmail($validatedData['email']);
        $password       = InputValidator::validatePassword($validatedData['password']);

        if (!$this->isPasswordStrong($password)) {
            throw new InvalidArgumentException('Lozinka mora sadržati bar jedan specijalni znak (npr. !@#$%) i izbegavati uobičajene kombinacije.');
        }

        if ($this->userRepository->userExists($username, $email)) {
            $this->logFailedAttempt(null, $username, $ipAddress);
            throw new InvalidArgumentException('Korisničko ime ili email već postoji.');
        }

        $hashedPassword = $this->hashPassword($password);

        $userData = [
            'username'           => $username,
            'email'              => $email,
            'password'           => $hashedPassword,
            'role'               => 'user',
            // ✅ 2FA je uvek uključen za sve korisnike
            'two_factor_enabled' => 1,
        ];

        try {
            $userId = $this->userRepository->create($userData);
            $this->userRepository->logAttempt($userId, $ipAddress, 1);
            $this->resetFailedAttempts($ipAddress);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Greška prilikom registracije: ' . $e->getMessage(), ['exception' => $e]);
            $this->logFailedAttempt(null, $username, $ipAddress);
            throw new RuntimeException('Došlo je do greške prilikom registracije.', 0, $e);
        }
    }

    public function login(string $username, string $password): ?array
    {
        $ipAddress = $this->getClientIp();

        if ($this->isRateLimited($ipAddress)) {
            $this->logger->warning("Rate limit premašen za IP: $ipAddress");
            return null;
        }

        // ✅ Samo trim i osnovna validacija — bez validatePassword() koji baca exception
        //    za lozinke koje ne odgovaraju registracionom formatu
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return null;
        }

        // Validacija formata korisničkog imena (samo format, ne lozinke)
        try {
            $username = InputValidator::validateUsername($username);
        } catch (InvalidArgumentException $e) {
            $this->logger->info("Login neuspešan — neispravan format username-a");
            return null;
        }

        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            $this->logFailedAttempt(null, $username, $ipAddress);
            $this->incrementRateLimit($ipAddress);
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            $this->logFailedAttempt($user['id'] ?? null, $username, $ipAddress);
            $this->incrementRateLimit($ipAddress);
            return null;
        }

        if ($this->needsRehash($user['password'])) {
            $this->updatePassword($user['id'], $password);
        }

        $this->userRepository->logAttempt($user['id'], $ipAddress, 1);
        $this->resetFailedAttempts($ipAddress);
        $this->resetRateLimit($ipAddress);

        $this->logger->info("Uspešan login za: {$username}, ID: {$user['id']}");

        unset($user['password']);
        return $user;
    }

    public function verifyTwoFactorCode(int $userId, string $code): bool
    {
        $row = $this->userRepository->getLatestTwoFactorCode($userId);

        if (!$row) {
            $this->logger->info("Nema važećeg 2FA koda za user_id: $userId");
            return false;
        }

        if ($row['code'] !== $code) {
            $this->logger->info("Pogrešan 2FA kod za user_id: $userId");
            return false;
        }

        $this->userRepository->deleteTwoFactorCodes($userId);
        $this->logger->info("2FA kod PRIHVAĆEN i obrisan za user_id: $userId");
        return true;
    }

    public function generateTwoFactorCode(int $userId): string
    {
        $code      = sprintf('%06d', random_int(0, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minuta

        $this->userRepository->deleteTwoFactorCodes($userId);
        $this->userRepository->saveTwoFactorCode($userId, $code, $expiresAt);

        $this->logger->info("2FA kod generisan za user_id: $userId");
        return $code;
    }

    public function generateAndSendTwoFactorCode(int $userId, string $username, string $email): string
    {
        $code = $this->generateTwoFactorCode($userId);
        $sent = $this->emailService->sendTwoFactorCode($email, $username, $code);

        if (!$sent) {
            $this->logger->warning("2FA email NIJE POSLAT za user_id: $userId");
        }

        return $code;
    }

    // ─────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    private function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    private function updatePassword(int $userId, string $newPassword): void
    {
        $newHash = $this->hashPassword($newPassword);
        $this->userRepository->updatePassword($userId, $newHash);
    }

    private function isRateLimited(string $ip): bool
    {
        $key      = "login_rate:{$ip}";
        $attempts = (int)($this->redis->get($key) ?? 0);
        return $attempts >= self::RATE_LIMIT_MAX;
    }

    private function isRateLimitedForRegister(string $ip): bool
    {
        $key      = "register_rate:{$ip}";
        $attempts = (int)($this->redis->get($key) ?? 0);
        return $attempts >= self::REGISTER_RATE_LIMIT_MAX;
    }

    private function incrementRateLimit(string $ip): void
    {
        $key      = "login_rate:{$ip}";
        $attempts = (int)($this->redis->get($key) ?? 0);
        $this->redis->set($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
    }

    private function resetRateLimit(string $ip): void
    {
        $this->redis->del("login_rate:{$ip}");
    }

    private function logFailedAttempt(?int $userId, string $username, string $ip): void
    {
        $this->logger->warning("Neuspešan login pokušaj", [
            'username' => $username,
            'ip'       => $ip,
            'user_id'  => $userId,
        ]);

        $key      = "failed_attempts:{$ip}";
        $attempts = (int)($this->redis->get($key) ?? 0) + 1;
        $this->redis->set($key, $attempts, self::BLOCK_DURATION);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->logger->warning("Previše neuspešnih pokušaja za IP: $ip — blokiran na 1 sat");
        }
    }

    private function resetFailedAttempts(string $ip): void
    {
        $this->redis->del("failed_attempts:{$ip}");
    }

    private function isPasswordStrong(string $password): bool
    {
        return (bool) preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);
    }

    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}