<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException
{
    public function __construct(string $message = 'Yêu cầu không hợp lệ', int $statusCode = 400)
    {
        parent::__construct($statusCode, $message);
    }
}
