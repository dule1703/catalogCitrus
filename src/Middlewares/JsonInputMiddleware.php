<?php
namespace App\Middlewares;

class JsonInputMiddleware
{
    public function process($request, callable $next)
    {
        // Proveri da li je zahtev JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'error' => 'Očekuje se Content-Type: application/json'
                ]
            ];
        }

        // Parsiraj JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'error' => 'Nevalidan JSON format'
                ]
            ];
        }

        // Prosledi input dalje kroz $request (najbolja praksa)
        $request['json'] = $input;

        return $next($request);
    }
}