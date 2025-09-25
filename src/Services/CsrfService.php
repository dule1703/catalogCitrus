<?php
namespace App\Services;

class CsrfService
{
    public function getHiddenInput(): string
    {
        $csrfToken = $_COOKIE['csrf_token'] ?? '';
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    }
}