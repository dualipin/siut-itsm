<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class LoanTransitionException extends HttpException
{
    public function __construct(string $message, int $statusCode = 409)
    {
        parent::__construct($statusCode, $message);
    }
}
