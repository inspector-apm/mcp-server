<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Reports;

class WorstTransactionsReport extends AbstractReport
{
    private array $transactions;
    private const SLOW_THRESHOLD_MS = 1000;
    private const MEMORY_THRESHOLD_MB = 50;
    private const PROBLEMATIC_HTTP_CODES = [400, 401, 403, 404, 422, 429, 500, 502, 503, 504];
    private const FAILED_RESULTS = ['failed', 'error', 'exception'];

    public function __construct(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function generate(): string
    {
        if (empty($this->transactions)) {
            return "No transactions found for the specified time range.";
        }

        $report = $this->generateHeader();

        // Categorize transactions for strategic analysis
        $categorized = $this->categorizeTransactions();

        // Priority 1: Critical Issues (Failed/Error transactions)
        if (!empty($categorized['critical'])) {
            $report .= $this->generateCriticalIssuesSection($categorized['critical']);
        }

        // Priority 2: Performance Issues (Slow transactions)
        if (!empty($categorized['slow'])) {
            $report .= $this->generatePerformanceIssuesSection($categorized['slow']);
        }

        // Priority 3: Memory Issues
        if (!empty($categorized['memory'])) {
            $report .= $this->generateMemoryIssuesSection($categorized['memory']);
        }

        // Priority 4: Other transactions
        if (!empty($categorized['other'])) {
            $report .= $this->generateOtherTransactionsSection($categorized['other']);
        }

        $report .= $this->generateSummaryAndRecommendations($categorized);

        return $report;
    }

    private function generateHeader(): string
    {
        $totalTransactions = \count($this->transactions);
        $timeRange = $this->getTimeRange();

        return "# Inspector - Worst Transactions Report\n\n" .
            "**Analysis Period:** {$timeRange}\n" .
            "**Total Transactions Analyzed:** {$totalTransactions}\n" .
            "**Generated:** " . \date('Y-m-d H:i:s') . "\n\n" .
            "---\n\n";
    }

    private function categorizeTransactions(): array
    {
        $categorized = [
            'critical' => [],
            'slow' => [],
            'memory' => [],
            'other' => []
        ];

        foreach ($this->transactions as $transaction) {
            if ($this->isCriticalTransaction($transaction)) {
                $categorized['critical'][] = $transaction;
            } elseif ($this->isSlowTransaction($transaction)) {
                $categorized['slow'][] = $transaction;
            } elseif ($this->isMemoryIntensiveTransaction($transaction)) {
                $categorized['memory'][] = $transaction;
            } else {
                $categorized['other'][] = $transaction;
            }
        }

        return $categorized;
    }

    private function generateCriticalIssuesSection(array $transactions): string
    {
        $section = "## CRITICAL ISSUES - Immediate Investigation Required\n\n";
        $section .= "These transactions have failed or returned error status codes. Investigate immediately.\n\n";

        foreach ($transactions as $transaction) {
            $section .= $this->generateCriticalTransactionReport($transaction);
        }

        return $section . "\n";
    }

    private function generateCriticalTransactionReport(array $transaction): string
    {
        $report = "### CRITICAL: {$transaction['name']}\n\n";
        $report .= "**Hash:** `{$transaction['hash']}`\n";
        $report .= "**Status:** {$transaction['result']} (FAILED)\n";
        $report .= "**Duration:** " . \number_format($transaction['duration'], 2) . "ms\n";
        $report .= "**Memory Peak:** " . \number_format($transaction['memory_peak'], 2) . "MB\n";
        $report .= "**Timestamp:** {$transaction['timestamp']}\n";
        $report .= "**Host:** {$transaction['host']['hostname']}\n\n";

        if (isset($transaction['http'])) {
            $report .= "**URL:** {$transaction['http']['url']['full']}\n";
            $report .= "**Method:** {$transaction['http']['request']['method']}\n\n";
        }

        $report .= "**INVESTIGATION STEPS:**\n";
        $report .= "1. Use hash `{$transaction['hash']}` to retrieve detailed timeline\n";
        $report .= "2. Check application logs around {$transaction['timestamp']}\n";
        $report .= "3. Review error handling in: `{$transaction['name']}`\n";

        if ($transaction['type'] === 'job') {
            $report .= "4. Check job queue configuration and implementation\n";
            $report .= "5. Verify job retry logic and failure handling\n";
        } elseif ($transaction['type'] === 'request') {
            $report .= "4. Check route implementation and middleware\n";
            $report .= "5. Verify request validation and error responses\n";
        }

        $report .= "\n---\n\n";

        return $report;
    }

    private function generatePerformanceIssuesSection(array $transactions): string
    {
        $section = "## PERFORMANCE ISSUES - Optimization Needed\n\n";
        $section .= "These transactions are significantly slower than expected. Likely candidates for optimization.\n\n";

        foreach ($transactions as $transaction) {
            $section .= $this->generateSlowTransactionReport($transaction);
        }

        return $section . "\n";
    }

    private function generateSlowTransactionReport(array $transaction): string
    {
        $durationSeconds = $transaction['duration'] / 1000;
        $severity = $this->getPerformanceSeverity($transaction['duration']);

        $report = "### {$severity} SLOW: {$transaction['name']}\n\n";
        $report .= "**Hash:** `{$transaction['hash']}`\n";
        $report .= "**Duration:** " . \number_format($transaction['duration'], 2) . "ms (" . \number_format($durationSeconds, 2) . "s)\n";
        $report .= "**Memory Peak:** " . \number_format($transaction['memory_peak'], 2) . "MB\n";
        $report .= "**Status:** {$transaction['result']}\n";
        $report .= "**Timestamp:** {$transaction['timestamp']}\n\n";

        if (isset($transaction['http'])) {
            $report .= "**URL:** {$transaction['http']['url']['full']}\n\n";
        }

        $report .= "**PERFORMANCE INVESTIGATION:**\n";
        $report .= "1. Retrieve timeline with hash `{$transaction['hash']}` to identify bottlenecks\n";
        $report .= "2. Look for N+1 query patterns in database operations\n";
        $report .= "3. Check for unoptimized loops or expensive computations\n";
        $report .= "4. Review external API calls and their response times\n";
        $report .= "5. Analyze cache hit/miss ratios\n";

        if ($transaction['type'] === 'job') {
            $report .= "6. Consider job chunking for large datasets\n";
        }

        $report .= "\n**COMMON SOLUTIONS:**\n";
        $report .= "- Add database indexes for slow queries\n";
        $report .= "- Implement caching for expensive operations\n";
        $report .= "- Use eager loading to prevent N+1 queries\n";
        $report .= "- Optimize or parallelize external API calls\n\n";

        $report .= "---\n\n";

        return $report;
    }

    private function generateMemoryIssuesSection(array $transactions): string
    {
        $section = "## MEMORY ISSUES - Resource Optimization\n\n";
        $section .= "These transactions consume significant memory. Consider memory optimization.\n\n";

        foreach ($transactions as $transaction) {
            $section .= $this->generateMemoryTransactionReport($transaction);
        }

        return $section . "\n";
    }

    private function generateMemoryTransactionReport(array $transaction): string
    {
        $report = "### HIGH MEMORY: {$transaction['name']}\n\n";
        $report .= "**Hash:** `{$transaction['hash']}`\n";
        $report .= "**Memory Peak:** " . \number_format($transaction['memory_peak'], 2) . "MB\n";
        $report .= "**Duration:** " . \number_format($transaction['duration'], 2) . "ms\n";
        $report .= "**Status:** {$transaction['result']}\n";
        $report .= "**Timestamp:** {$transaction['timestamp']}\n\n";

        $report .= "**MEMORY INVESTIGATION:**\n";
        $report .= "1. Use hash `{$transaction['hash']}` to analyze memory usage patterns\n";
        $report .= "2. Check for large dataset processing without chunking\n";
        $report .= "3. Look for memory leaks in loops or recursive functions\n";
        $report .= "4. Review object instantiation and garbage collection\n\n";

        $report .= "**OPTIMIZATION STRATEGIES:**\n";
        $report .= "- Implement data streaming for large datasets\n";
        $report .= "- Use generators instead of loading full arrays\n";
        $report .= "- Add explicit memory cleanup (unset variables)\n";
        $report .= "- Consider pagination for bulk operations\n\n";

        $report .= "---\n\n";

        return $report;
    }

    private function generateOtherTransactionsSection(array $transactions): string
    {
        $section = "## OTHER TRANSACTIONS - Baseline Performance\n\n";
        $section .= "These transactions are performing within acceptable parameters but are among the slowest in your application.\n\n";

        foreach ($transactions as $transaction) {
            $section .= $this->generateStandardTransactionReport($transaction);
        }

        return $section . "\n";
    }

    private function generateStandardTransactionReport(array $transaction): string
    {
        $report = "### {$transaction['name']}\n\n";
        $report .= "**Duration:** " . \number_format($transaction['duration'], 2) . "ms | ";
        $report .= "**Memory:** " . \number_format($transaction['memory_peak'], 2) . "MB | ";
        $report .= "**Status:** {$transaction['result']} | ";
        $report .= "**Hash:** `{$transaction['hash']}`\n\n";

        return $report;
    }

    private function generateSummaryAndRecommendations(array $categorized): string
    {
        $summary = "## SUMMARY & RECOMMENDATIONS\n\n";

        $criticalCount = \count($categorized['critical']);
        $slowCount = \count($categorized['slow']);
        $memoryCount = \count($categorized['memory']);
        $otherCount = \count($categorized['other']);

        $summary .= "**Issue Distribution:**\n";
        $summary .= "- Critical Issues: {$criticalCount}\n";
        $summary .= "- Performance Issues: {$slowCount}\n";
        $summary .= "- Memory Issues: {$memoryCount}\n";
        $summary .= "- Other: {$otherCount}\n\n";

        $summary .= "**GENERAL RECOMMENDATIONS:**\n";
        $summary .= "1. Use transaction hashes to retrieve detailed timelines for root cause analysis\n";
        $summary .= "3. Consider adding caching layers for frequently accessed, slow operations\n";
        $summary .= "4. Review and optimize database queries, especially in high-traffic endpoints\n";

        // todo: add alert tools to make agents able to create alerts based on performance metrics
        /*$summary .= "**NEXT STEPS:**\n";
        $summary .= "1. Investigate critical and slow transactions using their hash values\n";
        $summary .= "2. Set up alerts in Inspector for similar performance patterns\n";
        $summary .= "3. Implement fixes and monitor improvement over the next 24-48 hours\n";
        $summary .= "4. Consider load testing after optimizations to validate improvements\n\n";*/

        return $summary;
    }

    private function isCriticalTransaction(array $transaction): bool
    {
        if (\in_array($transaction['result'], self::FAILED_RESULTS)) {
            return true;
        }

        if (\is_numeric($transaction['result']) &&
            \in_array((int)$transaction['result'], self::PROBLEMATIC_HTTP_CODES)) {
            return true;
        }

        return false;
    }

    private function isSlowTransaction(array $transaction): bool
    {
        return $transaction['duration'] > self::SLOW_THRESHOLD_MS;
    }

    private function isMemoryIntensiveTransaction(array $transaction): bool
    {
        return $transaction['memory_peak'] > self::MEMORY_THRESHOLD_MB;
    }

    private function getPerformanceSeverity(float $duration): string
    {
        if ($duration > 10000) {
            return "CRITICAL";
        }
        if ($duration > 5000) {
            return "HIGH";
        }
        if ($duration > 2000) {
            return "MEDIUM";
        }
        return "LOW";
    }

    private function getTimeRange(): string
    {
        if (empty($this->transactions)) {
            return "No data available";
        }

        $timestamps = \array_column($this->transactions, 'timestamp');
        $earliest = \min($timestamps);
        $latest = \max($timestamps);

        if ($earliest === $latest) {
            return $earliest;
        }

        return "{$earliest} to {$latest}";
    }
}
