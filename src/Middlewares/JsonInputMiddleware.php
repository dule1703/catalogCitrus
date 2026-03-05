<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Response;

class JsonInputMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        if (stripos($contentType, 'application/json') === false) {
            return new Response(
                400,
                ['Content-Type' => 'application/json; charset=utf-8'],
                json_encode([
                    'error' => 'Očekuje se Content-Type: application/json'
                ], JSON_THROW_ON_ERROR)
            );
        }

        // Većina PSR-7 implementacija automatski parsira JSON u getParsedBody()
        $parsed = $request->getParsedBody();

        if ($parsed === null || $parsed === []) {
            // Ako nije parsirano → pokušaj ručno
            $body = (string) $request->getBody();
            $input = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Response(
                    400,
                    ['Content-Type' => 'application/json; charset=utf-8'],
                    json_encode([
                        'error' => 'Nevalidan JSON format: ' . json_last_error_msg()
                    ], JSON_THROW_ON_ERROR)
                );
            }

            // Dodaj u atribut da bi controller/video video
            $request = $request->withAttribute('json', $input);
        } else {
            $request = $request->withAttribute('json', $parsed);
        }

        return $handler->handle($request);
    }
}