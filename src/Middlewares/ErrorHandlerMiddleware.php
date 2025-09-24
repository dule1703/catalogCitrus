<?php
namespace App\Middlewares;

use Psr\Log\LoggerInterface;

class ErrorHandlerMiddleware
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Centralizovana obrada grešaka izvan middleware lanca (npr. u index.php)
     */
    public function handleException(\Throwable $e): void
    {
        $this->logger->error('Unhandled exception: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
        ]);

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Greška na serveru',
            'request_id' => $_SERVER['REQUEST_ID'] ?? null
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Obrada grešaka unutar middleware lanca
     */
    public function process($request, callable $next)
    {
        try {
            return $next($request);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Client error: ' . $e->getMessage(), [
                'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
            ]);

            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'error' => $e->getMessage(),
                    'request_id' => $_SERVER['REQUEST_ID'] ?? null
                ]
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Server error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
            ]);

            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'error' => 'Greška na serveru',
                    'request_id' => $_SERVER['REQUEST_ID'] ?? null
                ]
            ];
        }
    }
}