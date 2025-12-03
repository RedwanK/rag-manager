<?php

namespace App\Exception;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Rate limit exceeded. Please try again later.');
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
