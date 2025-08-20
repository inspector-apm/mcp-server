<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Reports;

use Inspector\MCPServer\App;

class ErrorsListReport extends AbstractReport
{
    public function __construct(protected App $app, protected array $errors)
    {
    }

    /**
     * Generate a comprehensive error report optimized for LLM analysis
     */
    public function generate(): string
    {
        if ($this->errors === []) {
            return "# Application Errors Report\n\n**No errors detected in the last 24 hours**\n\nThe application is currently running without any reported errors.";
        }

        $report = $this->buildHeader($this->errors);
        $report .= $this->buildExecutiveSummary($this->errors);
        $report .= $this->buildCriticalErrors($this->errors);
        $report .= $this->buildErrorBreakdown($this->errors);
        $report .= $this->buildRecommendations($this->errors);
        $report .= $this->buildFooter();

        return $report;
    }

    private function buildHeader(array $errors): string
    {
        $totalErrors = \count($errors);
        $totalOccurrences = \array_sum(\array_column($errors, 'nth'));
        $timestamp = \date('Y-m-d H:i:s T');

        return "# Application Errors Report - Last 24 hours\n" .
            '**Application:** '.$this->app->description().\PHP_EOL.
            "**Generated:** {$timestamp}\n" .
            "**Total Error Types:** {$totalErrors}\n" .
            "**Total Occurrences:** {$totalOccurrences}\n\n" .
            "---\n\n";
    }

    private function buildExecutiveSummary(array $errors): string
    {
        $highFrequencyErrors = \array_filter($errors, fn ($error) => $error['nth'] >= 10);
        $recentErrors = \array_filter(
            $errors,
            fn ($error) =>
            \strtotime($error['last_seen_at']) > \strtotime('-1 hour')
        );

        $errorClasses = \array_unique(\array_column($errors, 'class'));
        $affectedFiles = \array_unique(\array_column($errors, 'file'));

        $summary = "## Executive Summary\n\n";

        if (!empty($highFrequencyErrors)) {
            $summary .= "**HIGH PRIORITY:** " . \count($highFrequencyErrors) . " error type(s) with 10+ occurrences\n";
        }

        if (!empty($recentErrors)) {
            $summary .= "**ACTIVE:** " . \count($recentErrors) . " error type(s) occurred in the last hour\n";
        }

        $summary .= "**Error Categories:** " . \count($errorClasses) . " distinct exception types\n";
        $summary .= "**Affected Components:** " . \count($affectedFiles) . " different files/modules\n\n";

        return $summary;
    }

    private function buildCriticalErrors(array $errors): string
    {
        // Sort by frequency (nth) descending, then by recency
        \usort($errors, function ($a, $b) {
            if ($a['nth'] === $b['nth']) {
                return \strtotime($b['last_seen_at']) <=> \strtotime($a['last_seen_at']);
            }
            return $b['nth'] <=> $a['nth'];
        });

        $criticalErrors = \array_slice($errors, 0, 3); // Top 3 most critical

        $section = "## Critical Errors (Top 3 by Impact)\n\n";

        foreach ($criticalErrors as $index => $error) {
            $priority = $index + 1;
            $frequency = $this->getFrequencyLevel($error['nth']);
            $recency = $this->getRecencyIndicator($error['last_seen_at']);

            $section .= "### {$priority}. {$frequency} {$recency}\n";
            $section .= "**Error:** `{$error['class']}`\n";
            $section .= "**Message:** {$error['message']}\n";
            $section .= "**Occurrences:** {$error['nth']} times\n";
            $section .= "**First Seen:** {$error['created_at']}\n";
            $section .= "**Last Seen:** {$error['last_seen_at']}\n";
            $section .= "**Group Hash:** `{$error['group_hash']}` *(use this to get detailed stack trace, and bug fix suggestions)*\n\n";

            if (isset($error['app_file'])) {
                $section .= "**Application Source:**\n";
                $section .= "```php\n";
                $section .= "// File: {$error['app_file']['file']}:{$error['app_file']['line']}\n";
                $section .= $error['app_file']['code'] ?? '' . "\n";
                $section .= "```\n\n";
            }
        }

        return $section;
    }

    private function buildErrorBreakdown(array $errors): string
    {
        $section = "## Complete Error Breakdown\n\n";

        // Group by error class
        $byClass = [];
        foreach ($errors as $error) {
            $className = $error['class'];
            if (!isset($byClass[$className])) {
                $byClass[$className] = [];
            }
            $byClass[$className][] = $error;
        }

        foreach ($byClass as $className => $classErrors) {
            $totalOccurrences = \array_sum(\array_column($classErrors, 'nth'));
            $section .= "### `{$className}` ({$totalOccurrences} total occurrences)\n\n";

            foreach ($classErrors as $error) {
                $section .= "- **{$error['nth']}x** {$error['message']}\n";
                $section .= "  - `{$error['file']}:{$error['line']}`\n";
                $section .= "  - Group: `{$error['group_hash']}`\n";
                $section .= "  - Last: {$error['last_seen_at']}\n";

                if (isset($error['app_file'])) {
                    $section .= "  - App: `{$error['app_file']['file']}:{$error['app_file']['line']}`\n";
                }
                $section .= "\n";
            }
            $section .= "\n";
        }

        return $section;
    }

    private function buildRecommendations(array $errors): string
    {
        $section = "## AI Analysis & Recommendations\n\n";

        $section .= "### General Debugging Strategy\n";
        $section .= "1. **Monitor recency** - Prioritize errors that occurred in the last hour\n";
        $section .= "2. **Look at high-frequency errors** - Focus on errors with 10+ occurrences first\n";
        $section .= "3. **Check application code** - Review the app_file locations for business logic issues\n";
        $section .= "4. **Look for cascading failures** - Multiple errors in the same timeframe might be related\n\n";
        $section .= "5. **Use group hashes** - Call the error_details tool with *hash* for full stack traces and bug fix suggestions\n";

        return $section;
    }

    private function buildFooter(): string
    {
        return "---\n\n" .
            "*This report was generated by Inspector MCP Server for AI-assisted debugging.*\n" .
            "*Use the group_hash values to fetch detailed stack traces, bug fix suggestions, and context.*";
    }

    private function getFrequencyLevel(string $occurrences): string
    {
        if ($occurrences >= 50) {
            return "CRITICAL";
        }
        if ($occurrences >= 10) {
            return "HIGH";
        }
        if ($occurrences >= 5) {
            return "MEDIUM";
        }
        return "LOW";
    }

    private function getRecencyIndicator(string $lastSeen): string
    {
        $lastSeenTime = \strtotime($lastSeen);
        $now = \time();
        $diff = $now - $lastSeenTime;

        if ($diff < 300) {
            return "ACTIVE (< 5 min)";
        }
        if ($diff < 3600) {
            return "RECENT (< 1 hour)";
        }
        if ($diff < 21600) {
            return "RECENT (< 6 hours)";
        }
        return "STABLE (> 6 hours)";
    }
}
