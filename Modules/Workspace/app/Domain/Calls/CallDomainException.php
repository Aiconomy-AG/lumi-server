<?php

namespace Modules\Workspace\Domain\Calls;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class CallDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $statusCode = 409,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
