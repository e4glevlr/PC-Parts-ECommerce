<?php

namespace App\Exceptions;

class ResourceNotFoundException extends ApiException
{
    public function __construct(string $resource = 'Resource', string $field = 'id', mixed $value = null)
    {
        parent::__construct("{$resource} không tìm thấy với {$field}: '{$value}'", 404);
    }
}
