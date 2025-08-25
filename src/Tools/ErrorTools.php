<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tools;

use Inspector\MCPServer\HttpClientUtils;
use Inspector\MCPServer\Reports\ErrorReport;
use Inspector\MCPServer\Reports\ErrorsListReport;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use PhpMcp\Server\Contracts\SessionInterface;
use Psr\Log\LoggerInterface;

class ErrorTools
{
    use HttpClientUtils;

    public function __construct(
        protected LoggerInterface $logger
    ){
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    #[McpTool(name: 'get_production_errors', description: "Get recent production errors to debug and fix application issues. Returns a comprehensive analysis of errors, including frequency, severity, affected code locations, and AI-powered recommendations for resolution. Use this tool when investigating application problems, performance issues, or when you need to understand what's currently broken in production. Essential for proactive debugging and maintaining application reliability.")]
    public function listErrorsReport(
        #[Schema(description: 'The number of hours to look back for errors (24 by default).')]
        int $hours = 24,
        #[Schema(description: 'The maximum number of errors to return. Default null to return all errors.')]
        ?int $limit = null,
        SessionInterface $session = null,
    ): string {
        $this->logger->info("list errors session: ", $session?->all());

        $this->setApp();

        $start = \date('Y-m-d H:i:s', \strtotime("-{$hours} hours"));

        $errors = $this->httpClient()->get("errors?start={$start}")->getBody()->getContents();
        // Index errors with hash as the key
        $errors = \array_reduce(
            \json_decode($errors, true),
            fn ($carry, $item) => $carry + [$item['group_hash'] => $item],
            []
        );

        // Early return if there are too many errors in a single request
        if ($limit === null && \count($errors) > 10) {
            return "Current research for the last {$hours} hours retrieved more than 10 errors. They could flood the context window. "
                ."You can try to narrow the search by setting a limit or using a shorter time window.";
        }

        $errors = \array_slice($errors, 0, $limit);

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
}
