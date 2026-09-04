<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary\Tests;

use CleatSquad\ErrorBoundary\DefaultErrorResponseMapper;
use CleatSquad\ErrorBoundary\ErrorBoundary;
use CleatSquad\ErrorBoundary\ErrorResponseMapperInterface;
use PHPUnit\Framework\TestCase;

final class ErrorBoundaryTest extends TestCase
{
    public function testATimeoutAnswersGatewayTimeout(): void
    {
        [$status, $payload] = ErrorBoundary::responseFor([
            'type' => E_ERROR,
            'message' => 'Maximum execution time of 30 seconds exceeded',
        ]);

        $this->assertSame(504, $status);
        $this->assertSame('upstream_timeout', $payload['error']['code']);
    }

    public function testAnyOtherFatalAnswersInternalError(): void
    {
        [$status, $payload] = ErrorBoundary::responseFor([
            'type' => E_ERROR,
            'message' => 'Allowed memory size of 134217728 bytes exhausted',
        ]);

        $this->assertSame(500, $status);
        $this->assertSame('internal_error', $payload['error']['code']);
    }

    public function testTheEnvelopeIsAlwaysSerialisableJson(): void
    {
        foreach (['Maximum execution time of 30 seconds exceeded', 'Call to a member function on null'] as $message) {
            [, $payload] = ErrorBoundary::responseFor(['type' => E_ERROR, 'message' => $message]);

            $encoded = json_encode($payload);
            $this->assertIsString($encoded);
            $this->assertSame($payload, json_decode($encoded, true));
            $this->assertArrayHasKey('code', $payload['error']);
            $this->assertArrayHasKey('message', $payload['error']);
        }
    }

    public function testCustomMapperCanBeInjected(): void
    {
        $customMapper = new class () implements ErrorResponseMapperInterface {
            public function map(array $error): array
            {
                return [418, ['error' => ['code' => 'teapot', 'message' => 'I am a teapot']]];
            }
        };

        ErrorBoundary::setMapper($customMapper);
        [$status, $payload] = ErrorBoundary::responseFor(['type' => E_ERROR, 'message' => 'Custom error']);

        $this->assertSame(418, $status);
        $this->assertSame('teapot', $payload['error']['code']);

        // Reset mapper
        ErrorBoundary::setMapper(new DefaultErrorResponseMapper());
    }

    public function testTheClientIsToldNothingAboutTheInternals(): void
    {
        [, $payload] = ErrorBoundary::responseFor([
            'type' => E_ERROR,
            'message' => 'Maximum execution time of 30 seconds exceeded in /app/packages/llm-router/src/Driver/RetryingDriver.php on line 43',
        ]);

        $this->assertStringNotContainsString('/app/', $payload['error']['message']);
        $this->assertStringNotContainsString('RetryingDriver', $payload['error']['message']);
    }
}
