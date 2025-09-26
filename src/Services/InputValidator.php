<?php
namespace App\Services;

use InvalidArgumentException;

class InputValidator
{
    public static function sanitizeString(string $input): string
    {
        return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
    }

    public static function validateUsername(string $username): string
    {
        $username = self::sanitizeString($username);
        if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
            throw new InvalidArgumentException('Korisničko ime mora sadržati 3-20 alfanumeričkih karaktera, donju crtu ili crticu.');
        }
        return $username;
    }

    public static function validateEmail(string $email): string
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email adresa nije validna.');
        }
        return $email;
    }

    public static function validatePassword(string $password): string
    {
        if (strlen($password) < 8 || !preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $password)) {
            throw new InvalidArgumentException('Lozinka mora imati najmanje 8 karaktera i sadržati bar jedno slovo i jedan broj.');
        }
        // Dodatna preporuka: Specijalni znakovi (opcionalno za sada)
        // if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        //     throw new InvalidArgumentException('Lozinka mora sadržati bar jedan specijalni znak.');
        // }
        return $password;
    }

    public static function validateInputArray(array $data, array $requiredFields): array
    {
        $sanitized = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new InvalidArgumentException("Polje '$field' je obavezno.");
            }
            $sanitized[$field] = $data[$field];
        }
        return $sanitized;
    }
}