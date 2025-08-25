<?php

declare(strict_types=1);

if (\file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../../autoload.php';
}

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;

// create a log channel
$logger = new Logger('inspector-mcp');
$logger->pushHandler(new StreamHandler(__DIR__.'/../inspector-mcp.log', Level::Warning));

$server = Server::make()
    ->withServerInfo('Inspector MCP Server', '1.0.0')
    ->withLogger($logger)
    //->withCache($cache)     // Required for resumability
    ->build();

$server->discover(
    basePath: __DIR__,
    scanDirs: ['src/Tools']
);

// Create streamable transport with resumability
$transport = new StreamableHttpServerTransport(
    host: '127.0.0.1',      // MCP protocol prohibits 0.0.0.0
    port: 8080,
    mcpPath: '/',
    enableJsonResponse: false,  // Use SSE streaming (default)
    stateless: false            // Enable stateless mode for session-less clients
);

$server->listen($transport);
