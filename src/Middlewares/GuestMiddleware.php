<?php
namespace App\Middlewares;

use Psr\Container\ContainerInterface;
use App\Interfaces\MiddlewareInterface;


class GuestMiddleware implements MiddlewareInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(): ?array
    {
        if (isset($_COOKIE['jwt_token'])) {
            $jwtService = $this->container->get(\App\Services\JwtService::class);
            if ($jwtService->validate($_COOKIE['jwt_token'])) {
                header('Location: /dashboard');
                exit;
            }
        }
        return null;
    }
}