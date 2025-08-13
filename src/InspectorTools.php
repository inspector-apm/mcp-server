<?php

use PhpMcp\Server\Attributes\McpTool;

class InspectorTools
{
    #[McpTool(name: 'Errors', description: 'List of errors')]
    public function listErrors(): array
    {

    }

    #[McpTool(name: 'Error', description: 'Error details')]
    public function error(string $hash): array
    {

    }
}
