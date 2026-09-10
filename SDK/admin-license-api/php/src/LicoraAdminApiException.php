<?php
declare(strict_types=1);

namespace VibTools\Licora\AdminApi;

use RuntimeException;
use Throwable;

final class LicoraAdminApiException extends RuntimeException
{
    /** @var array<string, mixed>|null */
    private ?array $response;
    private string $apiCode;
    private int $httpStatus;
    private ?string $requestId;

    /** @param array<string, mixed>|null $response */
    public function __construct(
        string $message,
        string $apiCode = 'SDK_ERROR',
        int $httpStatus = 0,
        ?string $requestId = null,
        ?array $response = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->apiCode = $apiCode;
        $this->httpStatus = $httpStatus;
        $this->requestId = $requestId;
        $this->response = $response;
    }

    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** @return array<string, mixed>|null */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}

