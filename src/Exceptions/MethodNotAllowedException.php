<?php

namespace App\Exceptions;

use Exception;

class MethodNotAllowedException extends Exception
{
    public function __construct(string $message = "Metoda nije dozvoljena", int $code = 405)
    {
        parent::__construct($message, $code);
    }
}