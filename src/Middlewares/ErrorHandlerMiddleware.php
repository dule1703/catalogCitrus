<?php
namespace App\Middlewares;

use InvalidArgumentException;
use Exception;

class ErrorHandlerMiddleware implements \App\Interfaces\RequestMiddlewareInterface
{
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process($request, callable $next)
    {
        try {
            return $next($request);
        } catch (InvalidArgumentException $e) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => $e->getMessage()])
            ];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Greška na serveru'])
            ];
        }
    }
}