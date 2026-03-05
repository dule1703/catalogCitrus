<?php

namespace App\Services;

class CsrfService
{
    private const COOKIE_NAME = 'csrf_token';
    private const FIELD_NAME  = '_csrf_token';

    /**
     * Vraća ime hidden polja (za konzistentnost sa middleware-om)
     */
    public function getFieldName(): string
    {
        return self::FIELD_NAME;
    }

    /**
     * Generiše HTML hidden input sa datim tokenom
     */
    public function getHiddenInput(string $token): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::FIELD_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Opcionalno – ako želiš da servis sam generiše token (ali bolje da middleware to radi)
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}