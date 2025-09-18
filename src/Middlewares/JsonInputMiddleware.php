<?php
namespace App\Middlewares;

class JsonInputMiddleware implements \App\Interfaces\RequestMiddlewareInterface
{
    public function process($request, callable $next)
    {
        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Očekuje se application/json'])
            ];
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => 'Nevalidan JSON'])
            ];
        }

        $errors = [];
        if (empty($input['username'])) {
            $errors[] = 'Korisničko ime je obavezno';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $input['username'])) {
            $errors[] = 'Korisničko ime mora biti alfanumeričko, između 3 i 20 karaktera';
        }

        if (empty($input['email'])) {
            $errors[] = 'Email je obavezan';
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Nevalidan email format';
        }

        if (empty($input['password'])) {
            $errors[] = 'Lozinka je obavezna';
        } elseif (strlen($input['password']) < 8) {
            $errors[] = 'Lozinka mora imati najmanje 8 karaktera';
        }

        if (!empty($errors)) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['error' => $errors])
            ];
        }

        $GLOBALS['input'] = $input;
        return $next($request);
    }
}