<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Reports;

use Inspector\MCPServer\App;

class ErrorsListReport implements \Stringable
{
    public function __construct(protected App $app, protected array $errors)
    {
    }

    public function __toString(): string
    {
        return $this->generate();
    }

    /**
     * Generate a comprehensive error report optimized for LLM analysis
     */
    public function generate(): string
    {
        if ($this->errors === []) {
            return "# Application Errors Report\n\n✅ **No errors detected in the last 24 hours**\n\nThe application is currently running without any reported errors.";
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

        $summary = "## 📊 Executive Summary\n\n";

        if (!empty($highFrequencyErrors)) {
            $summary .= "🚨 **HIGH PRIORITY:** " . \count($highFrequencyErrors) . " error type(s) with 10+ occurrences\n";
        }

        if (!empty($recentErrors)) {
            $summary .= "⚡ **ACTIVE:** " . \count($recentErrors) . " error type(s) occurred in the last hour\n";
        }

        $summary .= "🏷️ **Error Categories:** " . \count($errorClasses) . " distinct exception types\n";
        $summary .= "📁 **Affected Components:** " . \count($affectedFiles) . " different files/modules\n\n";

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

        $section = "## 🚨 Critical Errors (Top 3 by Impact)\n\n";

        foreach ($criticalErrors as $index => $error) {
            $priority = $index + 1;
            $frequency = self::getFrequencyLevel($error['nth']);
            $recency = self::getRecencyIndicator($error['last_seen_at']);

            $section .= "### {$priority}. {$frequency} {$recency}\n";
            $section .= "**Error:** `{$error['class']}`\n";
            $section .= "**Message:** {$error['message']}\n";
            $section .= "**Occurrences:** {$error['nth']} times\n";
            $section .= "**First Seen:** {$error['created_at']}\n";
            $section .= "**Last Seen:** {$error['last_seen_at']}\n";
            $section .= "**Group Hash:** `{$error['group_hash']}` *(use this to get detailed stack trace)*\n\n";

            if (isset($error['app_file'])) {
                $section .= "**Application Source:**\n";
                $section .= "```php\n";
                $section .= "// File: {$error['app_file']['file']}:{$error['app_file']['line']}\n";
                $section .= \trim($error['app_file']['code']) . "\n";
                $section .= "```\n\n";
            }

            $section .= "**Immediate Action Required:** ";
            $section .= self::getSuggestedAction($error) . "\n\n";
            $section .= "---\n\n";
        }

        return $section;
    }

    private function buildErrorBreakdown(array $errors): string
    {
        $section = "## 📋 Complete Error Breakdown\n\n";

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
                $section .= "  - 📍 `{$error['file']}:{$error['line']}`\n";
                $section .= "  - 🔍 Group: `{$error['group_hash']}`\n";
                $section .= "  - ⏰ Last: {$error['last_seen_at']}\n";

                if (isset($error['app_file'])) {
                    $section .= "  - 🎯 App: `{$error['app_file']['file']}:{$error['app_file']['line']}`\n";
                }
                $section .= "\n";
            }
            $section .= "\n";
        }

        return $section;
    }

    private function buildRecommendations(array $errors): string
    {
        $section = "## 💡 AI Analysis & Recommendations\n\n";

        // Analyze patterns
        $patterns = self::analyzeErrorPatterns($errors);

        foreach ($patterns as $pattern) {
            $section .= "### {$pattern['title']}\n";
            $section .= "{$pattern['description']}\n\n";
            $section .= "**Recommended Actions:**\n";
            foreach ($pattern['actions'] as $action) {
                $section .= "- {$action}\n";
            }
            $section .= "\n";
        }

        $section .= "### 🔧 General Debugging Strategy\n";
        $section .= "1. **Start with high-frequency errors** - Focus on errors with 10+ occurrences first\n";
        $section .= "2. **Use group hashes** - Call the detailed error endpoint with group_hash for full stack traces\n";
        $section .= "3. **Check application code** - Review the app_file locations for business logic issues\n";
        $section .= "4. **Monitor recency** - Prioritize errors that occurred in the last hour\n";
        $section .= "5. **Look for cascading failures** - Multiple errors in the same timeframe might be related\n\n";

        return $section;
    }

    private function buildFooter(): string
    {
        return "---\n\n" .
            "*This report was generated by Inspector MCP Server for AI-assisted debugging.*\n" .
            "*Use the group_hash values to fetch detailed stack traces and context.*\n" .
            "*For production applications, consider implementing proper error handling and monitoring.*";
    }

    private function getFrequencyLevel(int $occurrences): string
    {
        if ($occurrences >= 50) {
            return "🔥 CRITICAL";
        }
        if ($occurrences >= 10) {
            return "⚠️ HIGH";
        }
        if ($occurrences >= 5) {
            return "⚡ MEDIUM";
        }
        return "📍 LOW";
    }

    private function getRecencyIndicator(string $lastSeen): string
    {
        $lastSeenTime = \strtotime($lastSeen);
        $now = \time();
        $diff = $now - $lastSeenTime;

        if ($diff < 300) {
            return "🟥 ACTIVE (< 5 min)";
        }
        if ($diff < 3600) {
            return "🟨 RECENT (< 1 hour)";
        }
        if ($diff < 21600) {
            return "🟨 RECENT (< 6 hours)";
        }
        return "🟩 STABLE (> 6 hours)";
    }

    private function getSuggestedAction(array $error): string
    {
        $message = \strtolower($error['message']);
        $class = $error['class'];

        // API/HTTP errors
        if (\str_contains($message, 'api.openai.com') || \str_contains($message, '400 bad request')) {
            return "Verify OpenAI API key, request format, and rate limits. Check Neuron AI framework configuration.";
        }

        if (\str_contains($class, 'ClientException') || \str_contains($message, 'client error')) {
            return "Review API request parameters, authentication, and endpoint URLs.";
        }

        if (\str_contains($class, 'ConnectionException') || \str_contains($message, 'connection')) {
            return "Check network connectivity, DNS resolution, and service availability.";
        }

        // Database errors
        if (\str_contains($class, 'PDO') || \str_contains($message, 'database')) {
            return "Verify database connection, query syntax, and table schema.";
        }

        // File system errors
        if (\str_contains($message, 'file') || \str_contains($message, 'permission')) {
            return "Check file permissions, disk space, and path validity.";
        }

        // Memory/Performance
        if (\str_contains($message, 'memory') || \str_contains($message, 'timeout')) {
            return "Optimize code performance, increase memory limits, or implement caching.";
        }

        return "Investigate the specific error context and implement appropriate error handling.";
    }

    private function analyzeErrorPatterns(array $errors): array
    {
        $patterns = [];

        // Check for API-related errors
        $apiErrors = \array_filter(
            $errors,
            fn ($e) =>
            \str_contains(\strtolower($e['message']), 'api') ||
            \str_contains($e['class'], 'ClientException')
        );

        if (!empty($apiErrors)) {
            $patterns[] = [
                'title' => '🌐 API Integration Issues Detected',
                'description' => 'Multiple API-related errors suggest integration or configuration problems.',
                'actions' => [
                    'Verify API credentials and endpoints',
                    'Check rate limiting and quotas',
                    'Implement proper retry mechanisms',
                    'Add request/response logging for debugging'
                ]
            ];
        }

        // Check for high-frequency errors
        $highFreq = \array_filter($errors, fn ($e) => $e['nth'] >= 10);
        if (!empty($highFreq)) {
            $patterns[] = [
                'title' => '🔄 Recurring Error Patterns',
                'description' => 'High-frequency errors indicate systematic issues that need immediate attention.',
                'actions' => [
                    'Implement circuit breaker patterns',
                    'Add comprehensive error handling',
                    'Review and optimize the affected code paths',
                    'Consider graceful degradation strategies'
                ]
            ];
        }

        // Check for recent spikes
        $recentErrors = \array_filter(
            $errors,
            fn ($e) =>
            \strtotime($e['last_seen_at']) > \strtotime('-2 hours')
        );

        if (\count($recentErrors) > \count($errors) * 0.5) {
            $patterns[] = [
                'title' => '📈 Recent Error Spike',
                'description' => 'A significant portion of errors occurred recently, suggesting a new issue or deployment problem.',
                'actions' => [
                    'Check recent deployments or configuration changes',
                    'Monitor system resources and dependencies',
                    'Consider rolling back recent changes if applicable',
                    'Implement additional monitoring and alerting'
                ]
            ];
        }

        return $patterns;
    }
}
