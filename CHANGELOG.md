# Changelog

All notable changes to `cleatsquad/php-error-boundary` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-04

### Added

- `ErrorBoundary::install()` accepts an optional PSR-3 `LoggerInterface` as a
  second argument. When supplied, the uncaught-exception line goes through
  `$logger->error()` (with the exception as context) instead of `error_log()`.
- `ErrorBoundary::uninstall()`, restoring PHP's default exception handler and
  making the shutdown handler already registered a no-op. Clears the mapper,
  logger and shutdown interceptor so a later `install()` starts clean.
- `ErrorBoundary::isInstalled()`.

### Changed

- New required dependency: `psr/log` (`^3.0`).

## [1.0.0] - 2026-09-04

Initial release.

### Added

- `ErrorBoundary`, installing a process-wide exception handler and shutdown
  handler that both answer fatals and uncaught exceptions with a JSON
  envelope instead of PHP's HTML error page.
- `ErrorResponseMapperInterface`/`DefaultErrorResponseMapper`, mapping an
  error to a `[statusCode, jsonPayload]` pair — 504 for a timeout, 500 for
  anything else — pluggable via `ErrorBoundary::setMapper()`.
- `ErrorBoundary::setShutdownInterception()`, letting a caller intercept
  before the standard JSON emit (e.g. an active SSE stream that needs the
  error framed as an event instead of a fresh HTTP response).

### Security

- The default mapper never returns the raw exception/error message, file
  path, or class name to the client — only a fixed code and a generic
  message.

[1.1.0]: https://github.com/CleatSquad/php-error-boundary/releases/tag/v1.1.0
[1.0.0]: https://github.com/CleatSquad/php-error-boundary/releases/tag/v1.0.0
