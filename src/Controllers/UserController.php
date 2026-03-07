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
        $this->userService    = $userService;
        $this->viewRenderer   = $viewRenderer;
        $this->logger         = $logger;
        $this->jwtService     = $jwtService;
        $this->csrfService    = $csrfService;
        $this->redis          = $redis;
        $this->userRepository = $userRepository;
    }

    private function getCsrfToken(ServerRequestInterface $request): string
    {
        return $request->getCookieParams()['csrf_token']
            ?? $request->getAttribute('csrf_token', '');
    }

    private function getIp(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['HTTP_X_FORWARDED_FOR'] ?? $p['REMOTE_ADDR'] ?? 'unknown';
    }

    // ─────────────────────────────────────────
    //  GET stranice
    // ─────────────────────────────────────────

    public function showLoginForm(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $content = $this->viewRenderer->render('user/login.php', [
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public function showRegisterForm(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $content = $this->viewRenderer->render('user/create.php', [
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public function showSuccess(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $content = $this->viewRenderer->render('user/success.php');
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public function showHome(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $token    = $request->getCookieParams()['jwt_token'] ?? null;
        $userData = $token ? $this->jwtService->getUserFromToken($token) : null;

        $user = null;
        if ($userData && isset($userData['id'])) {
            $user = $this->userRepository->findById($userData['id']);
        }

        $html = $this->viewRenderer->render('home.php', [
            'title'       => 'Početna',
            'user'        => $user,
            'csrfService' => $this->csrfService,
            'csrf_token'  => $this->getCsrfToken($request),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    // ─────────────────────────────────────────
    //  Logout
    //  Opoziva JWT i postavlja two_factor_enabled = 1
    //  tako da sledeći login zahteva 2FA
    // ─────────────────────────────────────────

    public function logout(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("logout called");

        $cookies      = $request->getCookieParams();
        $ip           = $this->getIp($request);
        $accessToken  = $cookies['jwt_token'] ?? null;
        $refreshToken = $cookies['refresh_token'] ?? null;

        // Opozovi JWT tokene u Redisu
        if ($accessToken)  $this->jwtService->revokeToken($accessToken);
        if ($refreshToken) $this->jwtService->revokeToken($refreshToken);

        // Postavi two_factor_enabled = 1 da sledeći login zahteva 2FA
        if ($accessToken) {
            $userData = $this->jwtService->getUserFromToken($accessToken);
            if ($userData) {
                $this->userRepository->enableTwoFactorFlag($userData['id']);
                $this->logger->info("two_factor_enabled = 1 postavljen pri logout za user: {$userData['id']}");
            }
        }

        // Obrisi Redis 2FA ključeve za ovu IP
        $keys = $this->redis->getClient()->keys("2fa_*:*:{$ip}");
        foreach ($keys as $key) {
            $this->redis->del($key);
        }

        $this->logger->info("Uspešan logout");

        return $this->jwtService->clearAuthCookies(new Response(302, ['Location' => '/']));
    }

    // ─────────────────────────────────────────
    //  Register
    // ─────────────────────────────────────────

    public function register(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $input = $this->getRequestData($request);
        $this->userService->registerUser($input);

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => 'Korisnik uspešno registrovan'], JSON_UNESCAPED_UNICODE)
        );
    }

    // ─────────────────────────────────────────
    //  Login
    //
    //  Tok:
    //  1. Validiraj kredencijale
    //  2. Proveri two_factor_enabled:
    //     - = 1 → pošalji 2FA kod, prikaži verify formu (BEZ JWT)
    //     - = 0 → izda JWT, /home
    // ─────────────────────────────────────────

    public function login(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("login called");

        $input    = $this->getRequestData($request);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->renderLogin($request, 'Korisničko ime i lozinka su obavezni', 400);
        }

        $ip   = $this->getIp($request);
        $user = $this->userService->login($username, $password);

        if (!$user) {
            $this->logger->warning("Login neuspešan za: $username");
            return $this->renderLogin($request, 'Pogrešno korisničko ime ili lozinka', 401);
        }

        $this->logger->info("Kredencijali OK za user: {$user['id']}, two_factor_enabled: {$user['two_factor_enabled']}");

        // two_factor_enabled = 1 → korisnik mora proći 2FA
        if ((int)($user['two_factor_enabled'] ?? 0) === 1) {
            $sentKey    = "2fa_sent:{$user['id']}:{$ip}";
            $pendingKey = "2fa_pending:{$user['id']}:{$ip}";

            if (!$this->redis->exists($sentKey)) {
                $this->userService->generateAndSendTwoFactorCode(
                    $user['id'],
                    $user['username'],
                    $user['email']
                );
                $this->redis->set($sentKey, '1', 600);
                $this->logger->info("2FA kod poslat za user: {$user['id']}");
            } else {
                $this->logger->info("2FA kod već poslat — preskačem slanje");
            }

            $this->redis->set($pendingKey, (string)$user['id'], 600);

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $this->viewRenderer->render('user/verify-2fa.php', [
                    'title'       => '2FA Verifikacija',
                    'csrfService' => $this->csrfService,
                    'csrf_token'  => $this->getCsrfToken($request),
                ])
            );
        }

        // two_factor_enabled = 0 → direktno uloguj
        $this->logger->info("2FA nije potreban za user: {$user['id']} — direktan login");
        return $this->issueTokensAndRedirect($user['id']);
    }

    // ─────────────────────────────────────────
    //  Verify 2FA
    //
    //  POST uspeh:
    //  1. Verifikuj kod
    //  2. Postavi two_factor_enabled = 0 u bazi
    //  3. Izda JWT, /home
    // ─────────────────────────────────────────

    public function verifyTwoFactor(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $this->logger->info("verifyTwoFactor called");

        $method = $request->getMethod();
        $ip     = $this->getIp($request);

        if ($method === 'GET') {
            $keys = $this->redis->getClient()->keys("2fa_pending:*:{$ip}");
            if (!$keys) {
                $this->logger->warning("Nema 2FA sesije na GET — redirect na login");
                return new Response(302, ['Location' => '/login']);
            }

            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $this->viewRenderer->render('user/verify-2fa.php', [
                    'title'       => '2FA Verifikacija',
                    'csrfService' => $this->csrfService,
                    'csrf_token'  => $this->getCsrfToken($request),
                ])
            );
        }

        if ($method === 'POST') {
            $input = $this->getRequestData($request);
            $code  = trim($input['code'] ?? '');

            if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
                return $this->renderVerify($request, 'Unesite validan 6-cifreni kod.', 422);
            }

            $keys = $this->redis->getClient()->keys("2fa_pending:*:{$ip}");
            if (!$keys) {
                $this->logger->warning("Nema 2FA sesije na POST — redirect na login");
                return new Response(302, ['Location' => '/login']);
            }

            $pendingKey = $keys[0];
            $userId     = $this->redis->get($pendingKey);

            if (!$userId) {
                return new Response(302, ['Location' => '/login']);
            }

            $userId  = (int)$userId;
            $isValid = $this->userService->verifyTwoFactorCode($userId, $code);

            if (!$isValid) {
                $this->logger->info("Pogrešan 2FA kod za user_id: $userId");
                return $this->renderVerify($request, 'Pogrešan ili istekli kod. Pokušaj ponovo.', 422);
            }

            // ✅ Verifikacija uspešna
            $this->redis->del($pendingKey);
            $this->redis->del("2fa_sent:{$userId}:{$ip}");
            $this->logger->info("2FA uspešno verifikovan za user_id: $userId");

            // ✅ Postavi two_factor_enabled = 0
            // Sledeći login neće zahtevati 2FA sve dok se ne odjavi (logout ga vraća na 1)
            $this->userRepository->disableTwoFactorFlag($userId);
            $this->logger->info("two_factor_enabled = 0 postavljen za user_id: $userId");

            return $this->issueTokensAndRedirect($userId);
        }

        return new Response(405, ['Allow' => 'GET, POST'], 'Method Not Allowed');
    }

    // ─────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────

    private function issueTokensAndRedirect(int $userId): ResponseInterface
    {
        [$accessToken, $refreshToken] = $this->jwtService->issueTokens($userId);
        $this->logger->info("JWT tokeni izdati za user_id: $userId");

        return $this->jwtService->setAuthCookies(
            $accessToken,
            $refreshToken,
            new Response(302, ['Location' => '/home'])
        );
    }

    private function renderLogin(ServerRequestInterface $request, string $error, int $status): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $this->viewRenderer->render('user/login.php', [
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request),
                'error'       => $error,
            ])
        );
    }

    private function renderVerify(ServerRequestInterface $request, string $error, int $status): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $this->viewRenderer->render('user/verify-2fa.php', [
                'title'       => '2FA Verifikacija',
                'csrfService' => $this->csrfService,
                'csrf_token'  => $this->getCsrfToken($request),
                'error'       => $error,
            ])
        );
    }

    private function getRequestData(ServerRequestInterface $request): array
    {
        $input = [];

        $body = (string) $request->getBody();
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input = $decoded;
            }
        }

        if (empty($input)) {
            $parsed = $request->getParsedBody();
            if (is_array($parsed)) {
                $input = $parsed;
            }
        }

        if (empty($input)) {
            $input = $request->getQueryParams();
        }

        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitized[$key] = is_string($value)
                ? InputValidator::sanitizeString($value)
                : $value;
        }

        return $sanitized;
    }
}