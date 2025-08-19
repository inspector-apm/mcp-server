#!/usr/bin/env php
<?php

declare(strict_types=1);

if (\file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../autoload.php';
}

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

try {
    // Build server configuration
    $server = Server::make()
        ->withServerInfo('Inspector MCP Server', '1.0.0')
        ->build();

    // Discover MCP elements via attributes
    $server->discover(
        basePath: __DIR__,
        scanDirs: ['src']
    );

    // Start listening via stdio transport
    $transport = new StdioServerTransport();
    $server->listen($transport);

} catch (\Throwable $e) {
    \fwrite(\STDERR, "[CRITICAL ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
