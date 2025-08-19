<?php

declare(strict_types=1);

namespace Inspector\MCPServer;

use Inspector\MCPServer\Reports\ErrorReport;
use Inspector\MCPServer\Reports\ErrorsListReport;
use PhpMcp\Server\Attributes\McpTool;

class InspectorElements
{
    use HttpClient;

    protected function getAppFileFromStack(array $stacktrace): ?array
    {
        foreach ($stacktrace as $frame) {
            if ($frame['in_app']) {
                $frame['code'] = \array_reduce($frame['code'], fn ($carry, $item) => $carry.\PHP_EOL.$item['line'].' | '.$item['code'], '');
                return $frame;
            }
        }

        return null;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    #[McpTool(name: 'get_production_errors', description: "Get recent production errors to debug and fix application issues. Returns a comprehensive analysis of errors that occurred in the last 24 hours, including frequency, severity, affected code locations, and AI-powered recommendations for resolution. Use this tool when investigating application problems, performance issues, or when you need to understand what's currently broken in production. Essential for proactive debugging and maintaining application reliability.")]
    public function listErrorsReport(): string
    {
        $this->setApp();

        $errors = $this->httpClient()->get("errors")->getBody()->getContents();
        // Index errors with hash as the key
        $errors = \array_reduce(
            \json_decode($errors, true),
            fn ($carry, $item) => $carry + [$item['group_hash'] => $item],
            []
        );

        $errorGroups = $this->httpClient()->post("error-groups", [
            'hashes' => \array_map(fn (array $error): string => $error['group_hash'], $errors)
        ])->getBody()->getContents();
        // Index error groups with hash as the key
        $errorGroups = \array_reduce(
            \json_decode($errorGroups, true),
            fn ($carry, $item) => $carry + [$item['hash'] => $item],
            []
        );

        // Merge error details with error groups
        foreach ($errors as $hash => $error) {
            $errors[$hash] = \array_merge($error, [
                'created_at' => $errorGroups[$hash]['created_at'] ?? '',
                'last_seen_at' => $errorGroups[$hash]['last_seen_at'] ?? '',
                'nth' => $errorGroups[$hash]['nth'] ?? '',
                'app_file' => $this->getAppFileFromStack($error['stack'] ?? [])
            ]);
        }

        return (string) new ErrorsListReport($this->app, \array_values($errors));
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    #[McpTool(name: 'get_error_analysis', description: 'Get detailed error analysis from the production environment including the actual application source file (not just library stack traces), error patterns, code context, occurrence frequency, and structured debugging guidance to help you fix the issue quickly.')]
    public function singleErrorReport(string $group_hash): string
    {
        $this->setApp();

        $error = $this->httpClient()->get("error-groups/{$group_hash}")->getBody()->getContents();
        $error = \json_decode($error, true);
        $error['app_file'] = $this->getAppFileFromStack($error['stack'] ?? []);

        return (string) new ErrorReport($error);
    }

    /*#[McpTool(name: 'worst_performing_transactions', description: 'Retrieve the list of WORST performing transactions.')]
    public function worstTransactions(): string
    {
        $this->setApp();
    }*/

    /*#[McpTool(name: 'transaction_timeline', description: 'Retrieve the timeline of a transaction.')]
    public function timeline(string $hash): string
    {
        $this->setApp();
    }*/
}
