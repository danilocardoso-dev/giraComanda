<?php

namespace App\Services;

use RuntimeException;

class AbacatePayApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null)
    {
        parent::__construct($message);
    }

    public function isDefinitiveRejection(): bool
    {
        return in_array($this->httpStatus, [400, 401, 403, 404, 422], true);
    }
}
