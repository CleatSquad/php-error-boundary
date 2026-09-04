<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary\Tests;

use CleatSquad\ErrorBoundary\DefaultErrorResponseMapper;
use CleatSquad\ErrorBoundary\ErrorBoundary;
use CleatSquad\ErrorBoundary\ErrorResponseMapperInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

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

    protected function tearDown(): void
    {
        // install() leaves a process-global exception handler behind; every
        // test that calls it must not leak into the next one.
        ErrorBoundary::uninstall();
        ErrorBoundary::setMapper(new DefaultErrorResponseMapper());
    }

    public function testInstallReportsItselfAsInstalled(): void
    {
        $this->assertFalse(ErrorBoundary::isInstalled());

        ErrorBoundary::install();

        $this->assertTrue(ErrorBoundary::isInstalled());
    }

    public function testUninstallReportsItselfAsNotInstalled(): void
    {
        ErrorBoundary::install();
        ErrorBoundary::uninstall();

        $this->assertFalse(ErrorBoundary::isInstalled());
    }

    public function testInstalledLoggerReceivesTheUncaughtExceptionLine(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Uncaught RuntimeException: boom'),
                $this->arrayHasKey('exception')
            );

        ErrorBoundary::install(null, $logger);
        $handler = set_exception_handler(static function (): void {
        });
        restore_exception_handler();
        if (!is_callable($handler)) {
            self::fail('set_exception_handler() did not return a callable.');
        }

        ob_start();
        $handler(new RuntimeException('boom'));
        ob_end_clean();
    }

    public function testWithNoLoggerTheHandlerStillAnswersWithoutThrowing(): void
    {
        ErrorBoundary::install();
        $handler = set_exception_handler(static function (): void {
        });
        restore_exception_handler();
        if (!is_callable($handler)) {
            self::fail('set_exception_handler() did not return a callable.');
        }

        ob_start();
        $handler(new RuntimeException('no logger configured'));
        $output = ob_end_clean();

        $this->assertTrue($output !== false);
    }

    public function testUninstallMakesTheCapturedExceptionHandlerInert(): void
    {
        ErrorBoundary::install();
        $handler = set_exception_handler(static function (): void {
        });
        restore_exception_handler();
        if (!is_callable($handler)) {
            self::fail('set_exception_handler() did not return a callable.');
        }

        ErrorBoundary::uninstall();

        ob_start();
        $handler(new RuntimeException('should be ignored'));
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
