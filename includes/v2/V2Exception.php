<?php
declare(strict_types=1);
final class V2Exception extends RuntimeException {
    private string $machineCode;
    private int $httpStatus;
    public function __construct(string $machineCode, string $message, int $httpStatus = 400) {
        parent::__construct($message);
        $this->machineCode = $machineCode;
        $this->httpStatus = $httpStatus;
    }
    public function machineCode(): string { return $this->machineCode; }
    public function httpStatus(): int { return $this->httpStatus; }
}
