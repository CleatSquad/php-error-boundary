<?php

declare(strict_types=1);

namespace CleatSquad\ErrorBoundary;

use Psr\Log\LoggerInterface;
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
    private static ?LoggerInterface $logger = null;
    /** @var (callable(array{type: int, message: string, file?: string, line?: int}): bool)|null */
    private static mixed $shutdownInterception = null;
    private static bool $installed = false;

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

    /**
     * @param ?LoggerInterface $logger Receives the uncaught-exception line this
     *                                 boundary would otherwise send to error_log().
     *                                 Fatals caught by the shutdown handler are not
     *                                 logged here: PHP has already written them to
     *                                 the error log itself by the time it fires.
     */
    public static function install(?ErrorResponseMapperInterface $mapper = null, ?LoggerInterface $logger = null): void
    {
        if ($mapper !== null) {
            self::$mapper = $mapper;
        }
        if ($logger !== null) {
            self::$logger = $logger;
        }
        self::$installed = true;

        // The body belongs to the response, never to PHP's error reporting.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_exception_handler(static function (Throwable $e): void {
            if (!self::$installed) {
                return;
            }

            $message = sprintf(
                'Uncaught %s: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            if (self::$logger !== null) {
                self::$logger->error($message, ['exception' => $e]);
            } else {
                error_log($message);
            }

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
            if (!self::$installed) {
                return;
            }

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
     * Restores PHP's default exception handler and marks this boundary inert
     * for the shutdown function already registered — PHP has no
     * `unregister_shutdown_function()`, so the callback stays registered but
     * becomes a no-op. A later `install()` reactivates it. Mapper, logger and
     * shutdown interceptor are cleared so a fresh `install()` starts clean.
     */
    public static function uninstall(): void
    {
        self::$installed = false;
        self::$mapper = null;
        self::$logger = null;
        self::$shutdownInterception = null;
        restore_exception_handler();
    }

    public static function isInstalled(): bool
    {
        return self::$installed;
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
