<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Yêu cầu xác thực')
    {
        parent::__construct($message, 401);
    }
}
