<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Shared\Enums\ErrorCode;
use App\Domain\Shared\ErrorMessages;
use Illuminate\Http\JsonResponse;
use Throwable;

final class ApiErrorResponse
{
    public function __construct(
        private readonly ErrorCode $code,
        private readonly ?string $customMessage = null,
        private readonly ?string $correlationId = null,
        private readonly array $details = [],
    ) {}

    /**
     * Create from an error code with default message.
     */
    public static function fromCode(ErrorCode $code, ?string $correlationId = null): self
    {
        return new self($code, null, $correlationId);
    }

    /**
     * Create from an error code with a custom message.
     */
    public static function fromCodeWithMessage(
        ErrorCode $code,
        string $message,
        ?string $correlationId = null
    ): self {
        return new self($code, $message, $correlationId);
    }

    /**
     * Create from a Throwable with internal error code.
     */
    public static function fromException(
        Throwable $exception,
        ?string $correlationId = null
    ): self {
        // Never expose internal exception details to users
        return new self(
            ErrorCode::InternalError,
            null,
            $correlationId,
            config('app.debug') ? ['exception' => $exception->getMessage()] : []
        );
    }

    /**
     * Create a standardized error response.
     */
    public function toResponse(): JsonResponse
    {
        $body = [
            'error' => [
                'code' => $this->code->value,
                'message' => $this->customMessage ?? ErrorMessages::getUserMessage($this->code),
                'retryable' => $this->code->isRetryable(),
            ],
        ];

        if ($this->correlationId !== null) {
            $body['error']['correlation_id'] = $this->correlationId;
        }

        if (! empty($this->details) && config('app.debug')) {
            $body['error']['details'] = $this->details;
        }

        return response()->json($body, $this->code->httpStatus());
    }
}
