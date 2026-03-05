<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Nyholm\Psr7\Response;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        // Čitanje APP_DEBUG (Dotenv već učitao promenljive)
        $debugEnv = getenv('APP_DEBUG') !== false 
            ? getenv('APP_DEBUG') 
            : ($_ENV['APP_DEBUG'] ?? '0');

        $this->debug = filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Client error: ' . $e->getMessage(), [
                'request_id' => $request->getAttribute('request_id', 'unknown')
            ]);

            return $this->createJsonErrorResponse(
                400,
                $e->getMessage(),
                $request->getAttribute('request_id', null),
                null
            );
        } catch (\Throwable $e) {
            $this->logger->error('Server error: ' . $e->getMessage(), [
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace'      => $e->getTraceAsString(),
                'request_id' => $request->getAttribute('request_id', 'unknown')
            ]);

            return $this->createJsonErrorResponse(
                500,
                'Greška na serveru',
                $request->getAttribute('request_id', null),
                $e   // prosleđujemo exception za debug info
            );
        }
    }

    /**
     * Kreira konzistentan JSON odgovor
     * U debug modu dodaje detalje greške
     */
    private function createJsonErrorResponse(
        int $status,
        string $message,
        ?string $requestId,
        ?\Throwable $exception = null
    ): ResponseInterface {
        $payload = [
            'error'      => $message,
            'request_id' => $requestId,
            'status'     => $status,
            'timestamp'  => date('c'),
        ];

        if ($this->debug && $exception) {
            $payload['debug'] = [
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'code'    => $exception->getCode(),
                'trace'   => array_filter(
                    explode("\n", $exception->getTraceAsString()),
                    fn($line) => trim($line) !== ''
                ),
            ];
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * Centralizovana obrada grešaka izvan middleware lanca (npr. u index.php)
     * Sinhronizovano sa debug postavkom
     */
    public function handleException(\Throwable $e): void
    {
        $this->logger->error('Unhandled exception: ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
        ]);

        $payload = [
            'error'      => 'Greška na serveru',
            'request_id' => $_SERVER['REQUEST_ID'] ?? null,
            'status'     => 500,
            'timestamp'  => date('c'),
        ];

        if ($this->debug) {
            $payload['debug'] = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'code'    => $e->getCode(),
                'trace'   => explode("\n", $e->getTraceAsString()),
            ];
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        exit;
    }
}