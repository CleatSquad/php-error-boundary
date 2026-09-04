# PHP Error Boundary

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
