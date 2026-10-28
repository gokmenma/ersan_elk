<?php
namespace App\Service;

use Exception;

class UetsApiException extends Exception
{
    private int $httpStatus;
    private int $retryAfter;

    public function __construct(string $message, int $httpStatus = 0, int $retryAfter = 0)
    {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
        $this->retryAfter = $retryAfter;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}
