<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiResponseException extends Exception
{
    public function __construct(string $message, protected int $status = 400, protected array $extra = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), ...$this->extra], $this->status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }
}
