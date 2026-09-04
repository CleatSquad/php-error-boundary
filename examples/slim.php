<?php

/**
 * Slim 4 already has its own error middleware for exceptions thrown inside
 * a route — this boundary is not a replacement for that. Its job is what
 * Slim's middleware structurally cannot reach: a PHP fatal, which is not a
 * Throwable and so never enters any try/catch, PSR-15 pipeline included.
 *
 *   Request
 *      |
 *      v
 *   Slim middleware stack
 *      |
 *      +-- Throwable  --> Slim's own ErrorMiddleware
 *      |
 *      +-- PHP fatal  --> (the pipeline is already gone — nothing here
 *                          runs) --> ErrorBoundary's shutdown handler
 *                                    --> JSON fallback
 *
 * Two routes below make that split concrete: /crash is a normal exception,
 * answered by Slim; /fatal is a genuine, uncatchable fatal, answered by
 * this library instead.
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
$app->addErrorMiddleware(displayErrorDetails: false, logErrors: true, logErrorDetails: false);

$app->get('/status', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/crash', function () {
    // A Throwable, thrown inside the pipeline: Slim's own ErrorMiddleware
    // catches this. ErrorBoundary never sees it — nothing to demonstrate
    // here beyond "the normal case is already someone else's job".
    throw new RuntimeException('Normal exception — Slim answers this one.');
});

$app->get('/fatal', function () {
    // A genuine PHP fatal, reproducible without external tooling: lower
    // the memory limit for this request only, then exceed it. Not a
    // Throwable — Slim's ErrorMiddleware, and any try/catch, is powerless
    // here. Only ErrorBoundary's shutdown handler answers.
    ini_set('memory_limit', '2M');
    $buffer = '';
    while (true) {
        $buffer .= str_repeat('x', 1_000_000);
    }
});

$app->run();
