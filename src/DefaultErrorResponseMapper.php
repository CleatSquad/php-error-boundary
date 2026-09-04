<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary;

use Override;

final class DefaultErrorResponseMapper implements ErrorResponseMapperInterface
{
    #[Override]
    public function map(array $error): array
    {
        $isTimeout = str_contains($error['message'], 'Maximum execution time');

        return $isTimeout
            ? [504, ['error' => [
                'code' => 'upstream_timeout',
                'message' => 'The request exceeded its execution budget before an answer was produced.',
            ]]]
            : [500, ['error' => [
                'code' => 'internal_error',
                'message' => 'The request could not be completed.',
            ]]];
    }
}
