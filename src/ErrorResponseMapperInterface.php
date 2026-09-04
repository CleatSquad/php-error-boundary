<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary;

interface ErrorResponseMapperInterface
{
    /**
     * @param array{type: int, message: string, file?: string, line?: int} $error
     * @return array{0: int, 1: array{error: array{code: string, message: string}}} Returns [statusCode, jsonPayload]
     */
    public function map(array $error): array;
}
