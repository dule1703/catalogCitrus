<?php
namespace App\Interfaces;

interface RequestMiddlewareInterface
{
    /**
     * @param mixed $request
     * @param callable $next
     * @return mixed
     */
    public function process($request, callable $next);
}