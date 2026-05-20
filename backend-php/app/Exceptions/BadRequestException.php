<?php

namespace App\Exceptions;

class BadRequestException extends ApiException
{
    public function __construct(string $message = 'Yêu cầu không hợp lệ')
    {
        parent::__construct($message, 400);
    }
}
