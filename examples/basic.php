<?php

/**
 * Minimal setup: install the boundary once, at the entry point of any
 * script that answers JSON, before any business logic runs.
 *
 * Run it: php examples/basic.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CleatSquad\ErrorBoundary\ErrorBoundary;

ErrorBoundary::install();

header('Content-Type: application/json');

// Anything below this line is covered: an uncaught exception or a PHP
// fatal now answers with the boundary's JSON envelope instead of an HTML
// error page.
function handleRequest(): array
{
    // Simulate a bug: a method call on a null value deep in application code.
    $user = null;

    return ['name' => $user->getName()];
}

echo json_encode(handleRequest());
