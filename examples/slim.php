<?php

/**
 * Slim 4 already has its own error middleware for caught exceptions
 * thrown inside a route — this boundary is not a replacement for that.
 * Its job is what Slim's middleware cannot reach: a PHP fatal (a timeout,
 * a memory limit) that never propagates as a Throwable at all, and would
 * otherwise write an HTML error page into a JSON API's response body.
 *
 * Run it (after `composer require --dev slim/slim slim/psr7` in your own
 * project): php -S localhost:8080 examples/slim.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CleatSquad\ErrorBoundary\ErrorBoundary;
use Slim\Factory\AppFactory;

// Installed first, before Slim's own middleware stack: it only ever answers
// when nothing else — including Slim's error middleware — got the chance to.
ErrorBoundary::install();

$app = AppFactory::create();

$app->get('/status', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/crash', function () {
    // A memory_limit exhaustion here (not simulated — genuinely uncatchable)
    // is exactly the case Slim's own error middleware cannot see coming:
    // it is a PHP fatal, not a Throwable. ErrorBoundary's shutdown handler
    // answers with the same JSON envelope regardless.
    throw new RuntimeException('Unreachable in this example — illustrates where the boundary sits.');
});

$app->run();
