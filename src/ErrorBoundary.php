<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary;

use Throwable;

/**
 * Keeps the HTTP contract when PHP itself gives up.
 *
 * A fatal — a timeout, a memory limit, an autoload failure — is not a Throwable
 * and no try/catch reaches it, so without a shutdown handler PHP writes an HTML
 * error page into a body the client expected as JSON.
 */
final class ErrorBoundary
{
    /** Fatals: nothing runs after them, so only a shutdown handler can answer. */
    public const FATAL_LEVELS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    private static ?ErrorResponseMapperInterface $mapper = null;
    /** @var (callable(array{type: int, message: string, file?: string, line?: int}): bool)|null */
    private static mixed $shutdownInterception = null;

    public static function setMapper(ErrorResponseMapperInterface $mapper): void
    {
        self::$mapper = $mapper;
    }

    /**
     * Optional interceptor before standard JSON emit (e.g. for active SSE stream).
     * If the interceptor returns true, standard emit is skipped.
     *
     * @param (callable(array{type: int, message: string, file?: string, line?: int}): bool)|null $interception
     */
    public static function setShutdownInterception(?callable $interception): void
    {
        self::$shutdownInterception = $interception;
    }

    public static function install(?ErrorResponseMapperInterface $mapper = null): void
    {
        if ($mapper !== null) {
            self::$mapper = $mapper;
        }

        // The body belongs to the response, never to PHP's error reporting.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_exception_handler(static function (Throwable $e): void {
            error_log(sprintf(
                'Uncaught %s: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            $error = [
                'type' => E_ERROR,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            if (self::$shutdownInterception !== null && (self::$shutdownInterception)($error)) {
                return;
            }

            [$status, $payload] = (self::$mapper ?? new DefaultErrorResponseMapper())->map($error);
            self::emit($status, $payload);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || ($error['type'] & self::FATAL_LEVELS) === 0) {
                return;
            }

            if (self::$shutdownInterception !== null && (self::$shutdownInterception)($error)) {
                return;
            }

            [$status, $payload] = (self::$mapper ?? new DefaultErrorResponseMapper())->map($error);
            self::emit($status, $payload);
        });
    }

    /**
     * Maps an error to status code and payload using current or default mapper.
     *
     * @param array{type: int, message: string, file?: string, line?: int} $error
     * @return array{0: int, 1: array{error: array{code: string, message: string}}}
     */
    public static function responseFor(array $error): array
    {
        return (self::$mapper ?? new DefaultErrorResponseMapper())->map($error);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function emit(int $status, array $payload): void
    {
        // Whatever the handler already wrote stays written: replacing a partial
        // body would corrupt a response that may well have been complete.
        if (headers_sent()) {
            return;
        }

        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    }
}
