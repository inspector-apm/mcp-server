<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Reports;

class TransactionDetailReport extends AbstractReport
{
    public function __construct(protected array $transaction, protected array $tasks)
    {
    }

    public function generate(): string
    {
        $report = [];

        // Header with transaction overview
        $report[] = $this->generateHeader();

        // Critical issues first (exceptions)
        $exceptions = $this->findExceptions();
        if (!empty($exceptions)) {
            $report[] = $this->generateExceptionReport($exceptions);
        }

        // Performance analysis
        $report[] = $this->generatePerformanceAnalysis();

        // Database query analysis
        $report[] = $this->generateDatabaseAnalysis();

        // Timeline summary
        $report[] = $this->generateTimelineSummary();

        return \implode("\n\n", \array_filter($report));
    }

    private function generateHeader(): string
    {
        $type = \strtoupper($this->transaction['type']);
        $name = $this->transaction['name'];
        $duration = \number_format($this->transaction['duration'], 2);
        $memory = $this->transaction['memory_peak'];
        $result = $this->transaction['result'];
        $timestamp = $this->transaction['timestamp'];

        $header = "TRANSACTION ANALYSIS REPORT\n";
        $header .= \str_repeat("=", 50) . "\n";
        $header .= "Transaction: {$type} {$name}\n";
        $header .= "Timestamp: {$timestamp}\n";
        $header .= "Duration: {$duration}ms\n";
        $header .= "Memory Peak: {$memory}MB\n";
        $header .= "Result: {$result}\n";

        if (isset($this->transaction['http']['url']['full'])) {
            $header .= "URL: " . $this->transaction['http']['url']['full'] . "\n";
        }

        return $header;
    }

    private function generateExceptionReport(array $exceptions): string
    {
        $report = "CRITICAL ISSUES - EXCEPTIONS FOUND\n";
        $report .= \str_repeat("=", 40) . "\n";

        foreach ($exceptions as $index => $exception) {
            $report .= "Exception #" . ($index + 1) . ":\n";
            $report .= "  Error: " . $exception['label'] . "\n";
            $report .= "  Time: " . \number_format($exception['start'], 2) . "ms into execution\n";
            $report .= "  Duration: " . \number_format($exception['duration'], 2) . "ms\n";

            if (isset($exception['app_file'])) {
                $report .= "  File: " . $exception['app_file']['file'] . "\n";
                $report .= "  Line: " . $exception['app_file']['line'] . "\n";

                if (isset($exception['app_file']['code'])) {
                    $report .= "  Code Context:\n" . $exception['app_file']['code'] . "\n";
                }
            }

            $report .= "\n";
        }

        $report .= "RECOMMENDATION: Address these exceptions immediately as they indicate runtime errors in your application.\n";

        return $report;
    }

    private function generatePerformanceAnalysis(): string
    {
        $report = "PERFORMANCE ANALYSIS\n";
        $report .= \str_repeat("=", 30) . "\n";

        $totalDuration = $this->transaction['duration'];
        $slowTasks = $this->findSlowTasks();

        $report .= "Total Transaction Duration: " . \number_format($totalDuration, 2) . "ms\n";

        if ($totalDuration > 1000) {
            $report .= "WARNING: Transaction duration exceeds 1 second. Consider optimization.\n";
        } elseif ($totalDuration > 500) {
            $report .= "NOTICE: Transaction duration is moderately slow (>500ms).\n";
        }

        if (!empty($slowTasks)) {
            $report .= "\nSlow Tasks (>100ms):\n";
            foreach ($slowTasks as $task) {
                $percentage = ($task['duration'] / $totalDuration) * 100;
                $report .= "  - " . $task['type'] . ": " . \number_format($task['duration'], 2) . "ms ";
                $report .= "(" . \number_format($percentage, 1) . "% of total time)\n";
                $report .= "    Query: " . $this->truncateString($task['label'], 80) . "\n";
            }
        }

        return $report;
    }

    private function generateDatabaseAnalysis(): string
    {
        $dbTasks = $this->findDatabaseTasks();

        if (empty($dbTasks)) {
            return "DATABASE ANALYSIS\n" . \str_repeat("=", 20) . "\nNo database queries detected in this transaction.\n";
        }

        $report = "DATABASE ANALYSIS\n";
        $report .= \str_repeat("=", 20) . "\n";

        $totalDbTime = \array_sum(\array_column($dbTasks, 'duration'));
        $queryCount = \count($dbTasks);
        $totalTransactionTime = $this->transaction['duration'];
        $dbPercentage = ($totalDbTime / $totalTransactionTime) * 100;

        $report .= "Total Database Time: " . \number_format($totalDbTime, 2) . "ms ";
        $report .= "(" . \number_format($dbPercentage, 1) . "% of transaction)\n";
        $report .= "Query Count: {$queryCount}\n";
        $report .= "Average Query Time: " . \number_format($totalDbTime / $queryCount, 2) . "ms\n\n";

        // Check for N+1 queries
        $nPlusOneIssues = $this->detectNPlusOneQueries($dbTasks);
        if (!empty($nPlusOneIssues)) {
            $report .= "POTENTIAL N+1 QUERY ISSUES DETECTED:\n";
            foreach ($nPlusOneIssues as $issue) {
                $report .= "  - Pattern: " . $this->truncateString($issue['pattern'], 60) . "\n";
                $report .= "    Occurrences: " . $issue['count'] . " times\n";
                $report .= "    Total Time: " . \number_format($issue['total_time'], 2) . "ms\n";
            }
            $report .= "\nRECOMMENDation: Consider using eager loading or batch queries to optimize these patterns.\n\n";
        }

        // Show slowest queries
        $slowestQueries = $this->findSlowestQueries($dbTasks, 3);
        if (!empty($slowestQueries)) {
            $report .= "SLOWEST DATABASE QUERIES:\n";
            foreach ($slowestQueries as $index => $query) {
                $report .= ($index + 1) . ". Duration: " . \number_format($query['duration'], 2) . "ms\n";
                $report .= "   Query: " . $this->truncateString($query['label'], 100) . "\n";
                $report .= "   Executed at: " . \number_format($query['start'], 2) . "ms\n\n";
            }
        }

        return $report;
    }

    private function generateTimelineSummary(): string
    {
        $report = "EXECUTION TIMELINE SUMMARY\n";
        $report .= \str_repeat("=", 30) . "\n";

        $typeGroups = [];
        foreach ($this->tasks as $task) {
            $type = $task['type'];
            if (!isset($typeGroups[$type])) {
                $typeGroups[$type] = ['count' => 0, 'total_time' => 0];
            }
            $typeGroups[$type]['count']++;
            $typeGroups[$type]['total_time'] += $task['duration'];
        }

        \arsort($typeGroups, \SORT_NUMERIC);

        foreach ($typeGroups as $type => $data) {
            $avgTime = $data['total_time'] / $data['count'];
            $report .= \ucfirst($type) . ": " . $data['count'] . " operations, ";
            $report .= \number_format($data['total_time'], 2) . "ms total ";
            $report .= "(avg: " . \number_format($avgTime, 2) . "ms)\n";
        }

        $report .= "\nFor detailed investigation, focus on the endpoint/job implementation:\n";
        if ($this->transaction['type'] === 'request' && isset($this->transaction['http']['url']['path'])) {
            $report .= "- Check route definition for: " . $this->transaction['http']['url']['path'] . "\n";
            $report .= "- Look for controller method handling this endpoint\n";
        }
        $report .= "- Review database query locations using the timeline timestamps\n";
        $report .= "- Consider adding database indexes for slow queries\n";
        $report .= "- Implement query result caching where appropriate\n";

        return $report;
    }

    private function findExceptions(): array
    {
        return \array_filter($this->tasks, fn ($task) => $task['type'] === 'exception');
    }

    private function findSlowTasks(float $threshold = 100.0): array
    {
        return \array_filter($this->tasks, fn ($task) => $task['duration'] > $threshold);
    }

    private function findDatabaseTasks(): array
    {
        return \array_filter($this->tasks, fn ($task) => \in_array($task['type'], ['mysql', 'postgres', 'sqlite', 'mongodb']));
    }

    private function detectNPlusOneQueries(array $dbTasks): array
    {
        $patterns = [];

        foreach ($dbTasks as $task) {
            // Normalize query to detect patterns
            $normalized = $this->normalizeQuery($task['label']);

            if (!isset($patterns[$normalized])) {
                $patterns[$normalized] = [
                    'pattern' => $task['label'],
                    'count' => 0,
                    'total_time' => 0
                ];
            }

            $patterns[$normalized]['count']++;
            $patterns[$normalized]['total_time'] += $task['duration'];
        }

        // Filter patterns that appear multiple times (potential N+1)
        return \array_filter($patterns, fn ($pattern) => $pattern['count'] > 3);
    }

    private function normalizeQuery(string $query): string
    {
        // Remove parameter placeholders and normalize whitespace
        $normalized = \preg_replace('/\?/', 'PARAM', $query);
        $normalized = \preg_replace('/\s+/', ' ', $normalized);
        $normalized = \trim(\strtolower($normalized));

        // Remove specific values that might make queries look different
        $normalized = \preg_replace('/= PARAM/', '= ?', $normalized);
        $normalized = \preg_replace('/in \([^)]+\)/', 'in (?)', $normalized);

        return $normalized;
    }

    private function findSlowestQueries(array $dbTasks, int $limit = 3): array
    {
        \usort($dbTasks, fn ($a, $b) => $b['duration'] <=> $a['duration']);
        return \array_slice($dbTasks, 0, $limit);
    }

    private function truncateString(string $str, int $length): string
    {
        return \strlen($str) > $length ? \substr($str, 0, $length) . '...' : $str;
    }
}
