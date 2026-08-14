<?php
declare(strict_types=1);

final class UpdateException extends RuntimeException
{
    private string $errorCode;
    private int $httpStatus;

    public function __construct(string $errorCode, string $message, int $httpStatus = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function errorCode(): string { return $this->errorCode; }
    public function httpStatus(): int { return $this->httpStatus; }
}
