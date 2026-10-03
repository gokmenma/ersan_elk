<?php
namespace App\Service;

final class EdmOperationException extends \RuntimeException
{
    public function __construct(public readonly string $kind, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
