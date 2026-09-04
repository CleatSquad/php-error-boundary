# PHP Error Boundary

[![Tests](https://github.com/CleatSquad/php-error-boundary/actions/workflows/tests.yml/badge.svg)](https://github.com/CleatSquad/php-error-boundary/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/CleatSquad/php-error-boundary/branch/main/graph/badge.svg)](https://codecov.io/gh/CleatSquad/php-error-boundary)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)](composer.json)

Deterministic fatal error and uncaught exception boundary for JSON APIs,
returning a standard JSON envelope instead of PHP's HTML error page.

A fatal — a timeout, a memory limit, an autoload failure — is not a
`Throwable`, so no `try`/`catch` reaches it. Left unhandled, PHP writes an
HTML error page into a response body the client expected as JSON. This
library installs an exception handler and a shutdown handler that both
answer with the same JSON contract.

## Installation

```bash
composer require cleatsquad/php-error-boundary
```

Requires PHP 8.2 or later. No runtime dependencies.

## Usage

### Install once, at the entry point

```php
use CleatSquad\ErrorBoundary\ErrorBoundary;

ErrorBoundary::install();
```

Every uncaught exception and every fatal error (`E_ERROR`, `E_PARSE`,
`E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR`) now answers with a JSON
body instead of an HTML one — a 504 for a timeout, a 500 for anything else.

### Customise the mapping

```php
use CleatSquad\ErrorBoundary\ErrorResponseMapperInterface;

final class MyMapper implements ErrorResponseMapperInterface
{
    public function map(array $error): array
    {
        // $error = ['type' => int, 'message' => string, 'file'?: string, 'line'?: int]
        return [500, ['error' => ['code' => 'internal_error', 'message' => 'Something went wrong.']]];
    }
}

ErrorBoundary::install(new MyMapper());
```

### Send the uncaught-exception line through your own logger

```php
ErrorBoundary::install(null, $psr3Logger);
```

When a PSR-3 `LoggerInterface` is supplied, the uncaught-exception line goes
through `$logger->error()` (the exception is passed as `['exception' => $e]`
context) instead of `error_log()`. Fatals caught by the shutdown handler are
not logged here — PHP has already written them to the error log itself by
the time it fires.

### Uninstall

```php
ErrorBoundary::uninstall();
```

Restores PHP's default exception handler and makes the boundary inert for
the shutdown handler already registered (PHP has no
`unregister_shutdown_function()`, so it stays registered but becomes a
no-op). Clears the mapper, logger and shutdown interceptor, so a later
`install()` starts clean. Useful in tests, or when handing control back to
another error-handling system for the rest of the process.

### Intercept before the standard response is emitted

Useful for a response format the boundary doesn't own by default — an
active Server-Sent Events stream, for instance, where the error must be
framed as an event rather than a fresh HTTP response.

```php
ErrorBoundary::setShutdownInterception(function (array $error): bool {
    // Return true to skip the standard JSON emit — you already answered.
    return false;
});
```

## Examples

Full, runnable-style snippets for common setups live in
[`examples/`](examples/):

- [`examples/basic.php`](examples/basic.php) — installing at the entry
  point of a plain PHP script.
- [`examples/psr3-logger.php`](examples/psr3-logger.php) — wiring a PSR-3
  logger (Monolog, or any other implementation).
- [`examples/slim.php`](examples/slim.php) — a Slim 4 application.
- [`examples/sse-interception.php`](examples/sse-interception.php) —
  intercepting a fatal mid-stream on an active Server-Sent Events response.

## Design notes

**No stack trace, no file path, ever reaches the client.** The default
mapper returns a fixed `internal_error`/`upstream_timeout` code and a
generic message — never `$error['message']` verbatim, which could leak an
internal path or class name.

**Fail-open by design.** If `headers_sent()` is already true, `emit()`
does nothing rather than corrupt a response that may already be partially
written.

**One static boundary per process.** The handler is process-global by
construction — that's what lets it catch a fatal PHP itself doesn't let you
`catch`. Call `install()` once, at the entry point.

## Testing

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, max level
```

## License

MIT. See [LICENSE](LICENSE).
