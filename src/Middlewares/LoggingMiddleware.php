<?php
namespace App\Middlewares;

use App\Interfaces\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class LoggingMiddleware implements MiddlewareInterface {
    private $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function process() {
        $this->logger->info(sprintf(
            'Zahtev: %s %s',
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI']
        ));
        return null;
    }
}