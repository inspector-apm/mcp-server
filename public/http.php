<?php

declare(strict_types=1);

if (\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../../autoload.php';
}

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PhpMcp\Server\Contracts\SessionInterface;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Session\ArraySessionHandler;
use PhpMcp\Server\Session\Session;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use Psr\Log\LoggerInterface;

try {
    // create a log channel
    $logger = new Logger('inspector-mcp');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../inspector-mcp.log', Level::Debug));

    // Session handler
    $sessionHandler = new ArraySessionHandler();
    $session = new Session($sessionHandler);

    // Set up the container for dependency injection
    $container = new BasicContainer();
    $container->set(LoggerInterface::class, $logger);

    $server = Server::make()
        ->withServerInfo('Inspector MCP Server', '1.0.0')
        ->withLogger($logger)
        ->withContainer($container)
        ->build();

    $server->discover(
        basePath: __DIR__.'/../',
        scanDirs: ['src/Tools']
    );

// Create streamable transport
    $transport = new StreamableHttpServerTransport(
        host: '127.0.0.1',      // MCP protocol prohibits 0.0.0.0
        port: 8080,
        mcpPath: '/',
        enableJsonResponse: false,  // Use SSE streaming (default)
        stateless: true            // Enable stateless mode for session-less clients
    );

    $server->listen($transport);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[MCP SERVER CRITICAL ERROR]\n");
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    fwrite(STDERR, 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
