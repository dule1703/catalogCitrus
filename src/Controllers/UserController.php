<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Repositories\UserRepository;
use App\View\ViewRenderer;
use App\Utilities\ApiResponse;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;
use App\Services\CsrfService;
use App\Services\InputValidator;
use App\RedisClient;


class UserController
{
    private UserService $userService;
    private UserRepository $userRepository;
    private ViewRenderer $viewRenderer;
    private LoggerInterface $logger;
    private JwtService $jwtService;
    private CsrfService $csrfService;
    private RedisClient $redis;

    public function __construct(
        UserService $userService,
        ViewRenderer $viewRenderer,
        LoggerInterface $logger,
        JwtService $jwtService,
        CsrfService $csrfService,
        RedisClient $redis,
        UserRepository $userRepository
    ) {
        $this->userService = $userService;
        $this->viewRenderer = $viewRenderer;
        $this->logger = $logger;
        $this->jwtService = $jwtService;
        $this->csrfService = $csrfService;
        $this->redis = $redis;
        $this->userRepository = $userRepository;
    }

    public function showLoginForm($request, $vars)
    {
        try {
            $this->logger->info("showLoginForm called");
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

    public function showRegisterForm($request, $vars)
    {
        try {
            $this->logger->info("showRegisterForm called");
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

    public function showSuccess($request, $vars)
    {
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

    public function logout($request, $vars)
{
    try {
        $this->logger->info("logout called");

        // OBRIŠI JWT KOLAČIĆE
        $this->jwtService->clearAuthCookies();

        // OBRIŠI SVE 2FA SESIJE IZ REDISA (ako postoje)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->redis->del("2fa_sent:*:$ip");
        $this->redis->del("2fa_pending:*:$ip");

        $this->logger->info("Uspešan logout – korisnik odjavljen");

        //REDIRECT NA LOGIN STRANU
        return $this->redirect('/');

    } catch (\Throwable $e) {
        $this->logger->error("logout failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return $this->redirect('/'); // ipak preusmeri
    }
}

    public function register($request, $vars)
    {
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

            // OBRIŠI STARE 2FA KLJUČEVE
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $this->redis->del("2fa_sent:*:$ip");
            $this->redis->del("2fa_pending:*:$ip");

            $user = $this->userService->login($username, $password);
            if (!$user) {
                $this->logger->warning("Login failed for user: $username");
                return [
                    'status' => 401,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode(['error' => 'Pogrešno korisničko ime ili lozinka'])
                ];
            }

            // === 2FA LOGIKA ===
            if (!empty($user['two_factor_enabled'])) {
                $sentKey = "2fa_sent:{$user['id']}:{$ip}";
                $pendingKey = "2fa_pending:{$user['id']}:{$ip}";

                if ($this->redis->exists($sentKey)) {
                    $this->logger->info("2FA kod VEĆ POSLAT – preskačem");
                } else {
                    $this->userService->generateAndSendTwoFactorCode($user['id'], $user['username'], $user['email']);
                    $this->redis->set($sentKey, '1', 600);
                    $this->redis->set($pendingKey, (string)$user['id'], 600);
                    $this->logger->info("2FA kod POSLAT i sesija sačuvana");
                }

                return $this->viewRenderer->render('user/verify-2fa.php', [
                    'title' => '2FA Verifikacija',
                    'csrfService' => $this->csrfService
                ]);
            }

            // === BEZ 2FA: ODMAH NA HOME SA $user ===
            [$accessToken, $refreshToken] = $this->jwtService->issueTokens($user['id']);
            $this->jwtService->setAuthCookies($accessToken, $refreshToken);
            $this->logger->info("Login uspešan – bez 2FA – ide na home");

            return $this->viewRenderer->render('home.php', [
                'title' => 'Početna',
                'user' => $user,
                'csrfService' => $this->csrfService
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("login method failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Greška na serveru'])
            ];
        }
    }

    public function verifyTwoFactor($request, $vars)
    {
        try {
            $this->logger->info("verifyTwoFactor called");
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // === GET: PRIKAŽI FORMU ===
            if ($method === 'GET') {
                $keys = $this->redis->getClient()->keys("2fa_pending:*:$ip");
                if (!$keys) {
                    $this->logger->warning("Nema 2FA sesije na GET – redirect na login");
                    return $this->redirect('/login');
                }

                return $this->viewRenderer->render('user/verify-2fa.php', [
                    'title' => '2FA Verifikacija',
                    'csrfService' => $this->csrfService
                ]);
            }

            // === POST: VERIFIKUJ KOD ===
            if ($method === 'POST') {
                $input = $this->getRequestData();
                $code = trim($input['code'] ?? '');

                if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
                    return $this->viewRenderer->render('user/verify-2fa.php', [
                        'title' => '2FA Verifikacija',
                        'error' => 'Unesite validan 6-cifreni kod.',
                        'csrfService' => $this->csrfService
                    ]);
                }

                $keys = $this->redis->getClient()->keys("2fa_pending:*:$ip");
                if (!$keys) {
                    $this->logger->warning("Nema 2FA sesije na POST – redirect na login");
                    return $this->redirect('/login');
                }

                $key = $keys[0];
                $userId = $this->redis->get($key);
                if (!$userId) return $this->redirect('/login');

                $userId = (int)$userId;
                $this->redis->del($key);

                $isValid = $this->userService->verifyTwoFactorCode($userId, $code);
                if (!$isValid) {
                    return $this->viewRenderer->render('user/verify-2fa.php', [
                        'title' => '2FA Verifikacija',
                        'error' => 'Pogrešan ili istekli kod.',
                        'csrfService' => $this->csrfService
                    ]);
                }

                // === USPEŠNA VERIFIKACIJA ===
                $this->logger->info("2FA uspešno verifikovan za user_id: $userId");

                // OBRIŠI SVE 2FA KLJUČEVE
                $this->redis->del("2fa_sent:$userId:$ip");
                $this->redis->del("2fa_pending:$userId:$ip");

                // DOBAVI KORISNIKA IZ BAZE
                $user = $this->userRepository->findById($userId); 
                if (!$user) {
                    $this->logger->error("Korisnik nije pronađen nakon 2FA: $userId");
                    return $this->redirect('/login');
                }

                // IZDAJ JWT
                [$accessToken, $refreshToken] = $this->jwtService->issueTokens($userId);
                $this->jwtService->setAuthCookies($accessToken, $refreshToken);

                // VRATI HOME SA $user I $csrfService
                return $this->viewRenderer->render('home.php', [
                    'title' => 'Početna',
                    'user' => $user,
                    'csrfService' => $this->csrfService
                ]);
            }

            return ['status' => 405, 'body' => 'Method Not Allowed'];

        } catch (\Throwable $e) {
            $this->logger->error("verifyTwoFactor failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->redirect('/login');
        }
    }
    
    private function getRequestData(): array
    {
        $input = [];
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input = $decoded;
                $this->logger->info("Got JSON input data");
            }
        }
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;           
        }
        if (empty($input) && !empty($_GET)) {
            $input = $_GET;           
        }
        $sanitized = [];
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = InputValidator::sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    private function redirect(string $url): array
    {
        return ['status' => 302, 'headers' => ['Location' => $url], 'body' => ''];
    }
}