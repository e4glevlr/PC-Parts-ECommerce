<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Không có quyền truy cập')
    {
        parent::__construct($message, 403);
    }
}
