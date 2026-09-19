<?php

namespace App\Exceptions;

use RuntimeException;


class GroqRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message, $status);
    }
}
