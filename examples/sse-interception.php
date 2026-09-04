<?php

/**
 * A Server-Sent Events stream is not a fresh HTTP response by the time a
 * fatal happens mid-stream: headers are already sent, and the client is
 * mid-way through reading `event:`/`data:` frames. Emitting the boundary's
 * standard JSON body there would corrupt the stream. The shutdown
 * interceptor lets the SSE endpoint answer with one last, well-formed SSE
 * event instead, and tell the boundary to skip its own emit.
 *
 * Run it: php examples/sse-interception.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CleatSquad\ErrorBoundary\ErrorBoundary;

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

ErrorBoundary::install();

ErrorBoundary::setShutdownInterception(function (array $error): bool {
    // Headers are already sent (text/event-stream) — frame the error as one
    // more SSE event instead of letting the boundary attempt a fresh
    // http_response_code()/header() pair.
    echo "event: error\n";
    echo 'data: ' . json_encode(['message' => 'The stream ended unexpectedly.']) . "\n\n";

    return true; // Skip the boundary's standard JSON emit — already answered.
});

function streamEvents(): void
{
    echo "event: message\n";
    echo 'data: ' . json_encode(['step' => 1]) . "\n\n";
    flush();

    // Simulate a bug partway through the stream.
    $state = null;
    echo 'data: ' . json_encode(['step' => $state->next()]) . "\n\n";
}

streamEvents();
