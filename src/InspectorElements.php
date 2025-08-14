<?php

use GuzzleHttp\Client;
use Inspector\MCPServer\HttpClient;
use PhpMcp\Server\Attributes\McpTool;

class InspectorElements
{
    use HttpClient;

    #[McpTool(name: 'get_app_id', description: 'Retrieve the app ID on the Inspector.dev dashboard associated to the current project.')]
    public function getAppID(): int|string
    {
        return $_ENV['APPDATA'];
    }

    #[McpTool(name: 'list_errors', description: 'Retrieve the list of production errors.')]
    public function listErrors(int $app_id): array
    {

    }

    #[McpTool(name: 'error', description: 'Get the error details like file, line, message, stacktrace, etc.')]
    public function error(int $app_id, string $hash): array
    {

    }

    #[McpTool(name: 'worst_performing_transactions', description: 'Retrieve the list of WORS transactions.')]
    public function worstTransactions(int $app_id): array
    {

    }

    #[McpTool(name: 'transaction_timeline', description: 'Retrieve the timeline of a transaction.')]
    public function timeline(int $app_id, string $hash): array
    {

    }
}
