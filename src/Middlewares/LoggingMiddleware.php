<?php
namespace App\Middlewares;

use Psr\Log\LoggerInterface;

class LoggingMiddleware
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process($request, callable $next)
    {
        $this->logger->info('HTTP zahtev primljen', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
        ]);

        return $next($request);
    }
}