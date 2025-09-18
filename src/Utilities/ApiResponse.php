<?php
namespace App\Utilities;

class ApiResponse
{
    public static function success($data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    public static function error(string|array $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'error' => $message
        ]);
        exit;
    }
}