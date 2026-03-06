<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Repositories\UserRepository;
use App\View\ViewRenderer;
use Psr\Log\LoggerInterface;
use App\Services\JwtService;
use App\Services\CsrfService;
use App\Services\InputValidator;
use App\RedisClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

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
        $this->userService      = $userService;
        $this->viewRenderer     = $viewRenderer;
        $this->logger           = $logger;
        $this->jwtService       = $jwtService;
        $this->csrfService      = $csrfService;
        $this->redis            = $redis;
        $this->userRepository   = $userRepository;
    }

    // ─────────────────────────────────────────
    //  Helper — CSRF token za re-render formi
    //  na POST zahtevu (atribut nije postavljen
    //  od strane CsrfMiddleware-a na POST)
    // ─────────────────────────────────────────
    private function getCsrfToken(ServerRequestInterface $request): string
    {
        return $request->getCookieParams()['csrf_token']
            ?? $request->getAttribute('csrf_token', '');
    }

    public function showLoginForm(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("showLoginForm called");

        $content = $this->viewRenderer->render('user/login.php', [
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request)
        ]);

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content
        );
    }

    public function showRegisterForm(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("showRegisterForm called");

        $content = $this->viewRenderer->render('user/create.php', [
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request)
        ]);

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content
        );
    }

    public function showSuccess(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("showSuccess called");

        $content = $this->viewRenderer->render('user/success.php');

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $content
        );
    }

    public function logout(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("logout called");

        $this->jwtService->clearAuthCookies();

        $serverParams = $request->getServerParams();
        $ip = $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? 'unknown';

        $this->redis->del("2fa_sent:*:$ip");
        $this->redis->del("2fa_pending:*:$ip");

        $this->logger->info("Uspešan logout – korisnik odjavljen");

        return new Response(302, ['Location' => '/']);
    }

    public function register(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("register called");

        $input = $this->getRequestData($request);
        $this->logger->info("Input data received: " . json_encode(array_keys($input)));

        $this->userService->registerUser($input);

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => 'Korisnik uspešno registrovan'], JSON_UNESCAPED_UNICODE)
        );
    }

    public function login(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("login called");

        $input    = $this->getRequestData($request);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        // Prazni kredencijali
        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty credentials");

            $content = $this->viewRenderer->render('user/login.php', [
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request),
                'error'       => 'Korisničko ime i lozinka su obavezni'
            ]);

            return new Response(
                400,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $content
            );
        }

        $serverParams = $request->getServerParams();
        $ip = $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? 'unknown';

        $this->redis->del("2fa_sent:*:$ip");
        $this->redis->del("2fa_pending:*:$ip");

        $user = $this->userService->login($username, $password);

        // Pogrešni kredencijali
        if (!$user) {
            $this->logger->warning("Login failed for user: $username");

            $content = $this->viewRenderer->render('user/login.php', [
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request),
                'error'       => 'Pogrešno korisničko ime ili lozinka'
            ]);

            return new Response(
                401,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $content
            );
        }

        // 2FA logika
        if (!empty($user['two_factor_enabled'])) {
            $sentKey    = "2fa_sent:{$user['id']}:{$ip}";
            $pendingKey = "2fa_pending:{$user['id']}:{$ip}";

            if ($this->redis->exists($sentKey)) {
                $this->logger->info("2FA kod VEĆ POSLAT – preskačem");
            } else {
                $this->userService->generateAndSendTwoFactorCode($user['id'], $user['username'], $user['email']);
                $this->redis->set($sentKey, '1', 600);
                $this->redis->set($pendingKey, (string)$user['id'], 600);
                $this->logger->info("2FA kod POSLAT i sesija sačuvana");
            }

            $html = $this->viewRenderer->render('user/verify-2fa.php', [
                'title'       => '2FA Verifikacija',
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request)
            ]);

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $html
            );
        }

        // Bez 2FA – izdaj token i prikaži home
        [$accessToken, $refreshToken] = $this->jwtService->issueTokens($user['id']);
        $this->jwtService->setAuthCookies($accessToken, $refreshToken);

        $this->logger->info("Login uspešan za korisnika: {$user['id']}");

        $html = $this->viewRenderer->render('home.php', [
            'title'       => 'Početna',
            'user'        => $user,
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request)
        ]);

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $html
        );
    }

    public function verifyTwoFactor(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("verifyTwoFactor called");

        $method       = $request->getMethod();
        $serverParams = $request->getServerParams();
        $ip           = $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? 'unknown';

        if ($method === 'GET') {
            $keys = $this->redis->getClient()->keys("2fa_pending:*:$ip");
            if (!$keys) {
                $this->logger->warning("Nema 2FA sesije na GET – redirect na login");
                return new Response(302, ['Location' => '/login']);
            }

            $html = $this->viewRenderer->render('user/verify-2fa.php', [
                'title'       => '2FA Verifikacija',
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request)
            ]);

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $html
            );
        }

        if ($method === 'POST') {
            $input = $this->getRequestData($request);
            $code  = trim($input['code'] ?? '');

            // Neispravan format koda
            if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
                $html = $this->viewRenderer->render('user/verify-2fa.php', [
                    'title'       => '2FA Verifikacija',
                    'error'       => 'Unesite validan 6-cifreni kod.',
                    'csrfService' => $this->csrfService,
                    'csrf_token'  => $this->getCsrfToken($request)
                ]);

                return new Response(
                    422,
                    ['Content-Type' => 'text/html; charset=utf-8'],
                    $html
                );
            }

            $keys = $this->redis->getClient()->keys("2fa_pending:*:$ip");
            if (!$keys) {
                $this->logger->warning("Nema 2FA sesije na POST – redirect na login");
                return new Response(302, ['Location' => '/login']);
            }

            $key    = $keys[0];
            $userId = $this->redis->get($key);
            if (!$userId) {
                return new Response(302, ['Location' => '/login']);
            }

            $userId = (int)$userId;
            $this->redis->del($key);

            $isValid = $this->userService->verifyTwoFactorCode($userId, $code);

            // Pogrešan ili istekao kod
            if (!$isValid) {
                $html = $this->viewRenderer->render('user/verify-2fa.php', [
                    'title'       => '2FA Verifikacija',
                    'error'       => 'Pogrešan ili istekli kod.',
                    'csrfService' => $this->csrfService,
                    'csrf_token'  => $this->getCsrfToken($request)
                ]);

                return new Response(
                    422,
                    ['Content-Type' => 'text/html; charset=utf-8'],
                    $html
                );
            }

            $this->logger->info("2FA uspešno verifikovan za user_id: $userId");

            $this->redis->del("2fa_sent:$userId:$ip");
            $this->redis->del("2fa_pending:$userId:$ip");

            $user = $this->userRepository->findById($userId);
            if (!$user) {
                $this->logger->error("Korisnik nije pronađen nakon 2FA: $userId");
                return new Response(302, ['Location' => '/login']);
            }

            [$accessToken, $refreshToken] = $this->jwtService->issueTokens($userId);
            $this->jwtService->setAuthCookies($accessToken, $refreshToken);

            $html = $this->viewRenderer->render('home.php', [
                'title'       => 'Početna',
                'user'        => $user,
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request)
            ]);

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $html
            );
        }

        return new Response(405, ['Allow' => 'GET, POST'], 'Method Not Allowed');
    }

    private function getRequestData(ServerRequestInterface $request): array
    {
        $input = [];

        // JSON body
        $body = (string) $request->getBody();
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input = $decoded;
                $this->logger->info("Got JSON input data");
            }
        }

        // Form data
        if (empty($input)) {
            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody)) {
                $input = $parsedBody;
            }
        }

        // Query params kao fallback
        if (empty($input)) {
            $input = $request->getQueryParams();
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
}