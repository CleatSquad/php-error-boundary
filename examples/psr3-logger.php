<?php

/**
 * Sending the uncaught-exception line through a PSR-3 logger instead of
 * error_log(). Works with any PSR-3 implementation — Monolog is shown here
 * because it is the most common one, not because this library depends on
 * it (it doesn't: only psr/log is required).
 *
 * Run it (after `composer require --dev monolog/monolog` in your own
 * project): php examples/psr3-logger.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CleatSquad\ErrorBoundary\ErrorBoundary;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// 12-factor style: write structured logs to stdout, let the container
// runtime (Docker, FrankenPHP, Kubernetes) collect them from there.
$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout'));

ErrorBoundary::install(null, $logger);

header('Content-Type: application/json');

function handleRequest(): array
{
    throw new RuntimeException('Upstream payment provider is unreachable.');
}

echo json_encode(handleRequest());

// stdout now carries a structured log line for this exception, in addition
// to the JSON envelope the client received — one event, two destinations.
