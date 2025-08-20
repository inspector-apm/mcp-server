<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tests;

use Inspector\MCPServer\Reports\WorstTransactionsReport;
use PHPUnit\Framework\TestCase;

class WorstTransactionsReportTest extends TestCase
{
    private function createSampleTransaction(array $overrides = []): array
    {
        return \array_merge([
            'hash' => 'abc123def456',
            'name' => 'App\Http\Controllers\UserController@index',
            'type' => 'request',
            'result' => 'success',
            'duration' => 500.0,
            'memory_peak' => 25.5,
            'timestamp' => '2023-12-01 10:30:00',
            'host' => [
                'hostname' => 'web-server-01'
            ],
            'http' => [
                'url' => [
                    'full' => 'https://example.com/api/users'
                ],
                'request' => [
                    'method' => 'GET'
                ]
            ]
        ], $overrides);
    }

    public function testEmptyTransactionsReturnsNoDataMessage(): void
    {
        $report = new WorstTransactionsReport([]);
        $result = $report->generate();

        $this->assertStringContainsString('No transactions found for the specified time range.', $result);
    }

    public function testReportHeaderGeneration(): void
    {
        $transactions = [$this->createSampleTransaction()];
        $report = new WorstTransactionsReport($transactions);
        $result = $report->generate();

        $this->assertStringContainsString('# Inspector - Worst Transactions Report', $result);
        $this->assertStringContainsString('**Total Transactions Analyzed:** 1', $result);
        $this->assertStringContainsString('**Generated:**', $result);
        $this->assertStringContainsString(\date('Y-m-d'), $result);
    }

    public function testCriticalTransactionWithFailedResult(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'failed',
            'name' => 'FailedTransaction'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## CRITICAL ISSUES - Immediate Investigation Required', $result);
        $this->assertStringContainsString('### CRITICAL: FailedTransaction', $result);
        $this->assertStringContainsString('**Status:** failed (FAILED)', $result);
        $this->assertStringContainsString('**INVESTIGATION STEPS:**', $result);
        $this->assertStringContainsString('Use hash `abc123def456`', $result);
    }

    public function testCriticalTransactionWithErrorResult(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'error'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## CRITICAL ISSUES', $result);
        $this->assertStringContainsString('**Status:** error (FAILED)', $result);
    }

    public function testCriticalTransactionWithExceptionResult(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'exception'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## CRITICAL ISSUES', $result);
        $this->assertStringContainsString('**Status:** exception (FAILED)', $result);
    }

    public function testCriticalTransactionWithHttpErrorCodes(): void
    {
        $errorCodes = [400, 401, 403, 404, 422, 429, 500, 502, 503, 504];

        foreach ($errorCodes as $code) {
            $transaction = $this->createSampleTransaction([
                'result' => (string)$code
            ]);

            $report = new WorstTransactionsReport([$transaction]);
            $result = $report->generate();

            $this->assertStringContainsString('## CRITICAL ISSUES', $result);
            $this->assertStringContainsString("**Status:** {$code} (FAILED)", $result);
        }
    }

    public function testJobTypeTransactionInvestigationSteps(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'failed',
            'type' => 'job'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('Check job queue configuration', $result);
        $this->assertStringContainsString('Verify job retry logic', $result);
    }

    public function testRequestTypeTransactionInvestigationSteps(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'failed',
            'type' => 'request'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('Check route implementation', $result);
        $this->assertStringContainsString('Verify request validation', $result);
    }

    public function testSlowTransactionWithLowSeverity(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 1500.0, // Above 1000ms threshold
            'name' => 'SlowTransaction'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## PERFORMANCE ISSUES - Optimization Needed', $result);
        $this->assertStringContainsString('### LOW SLOW: SlowTransaction', $result);
        $this->assertStringContainsString('**Duration:** 1,500.00ms (1.50s)', $result);
        $this->assertStringContainsString('**PERFORMANCE INVESTIGATION:**', $result);
        $this->assertStringContainsString('**COMMON SOLUTIONS:**', $result);
    }

    public function testSlowTransactionWithMediumSeverity(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 3000.0
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('### MEDIUM SLOW:', $result);
    }

    public function testSlowTransactionWithHighSeverity(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 7000.0
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('### HIGH SLOW:', $result);
    }

    public function testSlowTransactionWithCriticalSeverity(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 15000.0
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('### CRITICAL SLOW:', $result);
    }

    public function testJobTypeSlowTransactionRecommendations(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 2000.0,
            'type' => 'job'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('Consider job chunking for large datasets', $result);
    }

    public function testMemoryIntensiveTransaction(): void
    {
        $transaction = $this->createSampleTransaction([
            'memory_peak' => 75.5, // Above 50MB threshold
            'name' => 'MemoryHungryTransaction'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## MEMORY ISSUES - Resource Optimization', $result);
        $this->assertStringContainsString('### HIGH MEMORY: MemoryHungryTransaction', $result);
        $this->assertStringContainsString('**Memory Peak:** 75.50MB', $result);
        $this->assertStringContainsString('**MEMORY INVESTIGATION:**', $result);
        $this->assertStringContainsString('**OPTIMIZATION STRATEGIES:**', $result);
        $this->assertStringContainsString('Use generators instead of loading full arrays', $result);
    }

    public function testOtherTransactionsSection(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 500.0, // Below slow threshold
            'memory_peak' => 25.0, // Below memory threshold
            'result' => 'success',
            'name' => 'NormalTransaction'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## OTHER TRANSACTIONS - Baseline Performance', $result);
        $this->assertStringContainsString('### NormalTransaction', $result);
        $this->assertStringContainsString('**Duration:** 500.00ms', $result);
        $this->assertStringContainsString('**Memory:** 25.00MB', $result);
        $this->assertStringContainsString('**Status:** success', $result);
    }

    public function testTransactionWithoutHttpData(): void
    {
        $transaction = $this->createSampleTransaction([
            'result' => 'failed',
            'type' => 'job'
        ]);
        unset($transaction['http']);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('## CRITICAL ISSUES', $result);
        $this->assertStringNotContainsString('**URL:**', $result);
        $this->assertStringNotContainsString('**Method:**', $result);
    }

    public function testSummaryAndRecommendations(): void
    {
        $transactions = [
            $this->createSampleTransaction(['result' => 'failed']), // Critical
            $this->createSampleTransaction(['duration' => 2000.0]), // Slow
            $this->createSampleTransaction(['memory_peak' => 75.0]), // Memory
            $this->createSampleTransaction(['duration' => 500.0]) // Other
        ];

        $report = new WorstTransactionsReport($transactions);
        $result = $report->generate();

        $this->assertStringContainsString('## SUMMARY & RECOMMENDATIONS', $result);
        $this->assertStringContainsString('**Issue Distribution:**', $result);
        $this->assertStringContainsString('- Critical Issues: 1', $result);
        $this->assertStringContainsString('- Performance Issues: 1', $result);
        $this->assertStringContainsString('- Memory Issues: 1', $result);
        $this->assertStringContainsString('- Other: 1', $result);
        $this->assertStringContainsString('**GENERAL RECOMMENDATIONS:**', $result);
    }

    public function testTimeRangeSingleTransaction(): void
    {
        $transaction = $this->createSampleTransaction([
            'timestamp' => '2023-12-01 10:30:00'
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('**Analysis Period:** 2023-12-01 10:30:00', $result);
    }

    public function testTimeRangeMultipleTransactions(): void
    {
        $transactions = [
            $this->createSampleTransaction(['timestamp' => '2023-12-01 10:00:00']),
            $this->createSampleTransaction(['timestamp' => '2023-12-01 11:00:00']),
            $this->createSampleTransaction(['timestamp' => '2023-12-01 10:30:00'])
        ];

        $report = new WorstTransactionsReport($transactions);
        $result = $report->generate();

        $this->assertStringContainsString('**Analysis Period:** 2023-12-01 10:00:00 to 2023-12-01 11:00:00', $result);
    }

    public function testTransactionPrioritization(): void
    {
        $transactions = [
            $this->createSampleTransaction([
                'name' => 'NormalTransaction',
                'duration' => 500.0
            ]),
            $this->createSampleTransaction([
                'name' => 'SlowTransaction',
                'duration' => 2000.0
            ]),
            $this->createSampleTransaction([
                'name' => 'FailedTransaction',
                'result' => 'failed'
            ]),
            $this->createSampleTransaction([
                'name' => 'MemoryTransaction',
                'memory_peak' => 75.0
            ])
        ];

        $report = new WorstTransactionsReport($transactions);
        $result = $report->generate();

        // Check that critical issues appear first
        $criticalPos = \strpos($result, '## CRITICAL ISSUES');
        $performancePos = \strpos($result, '## PERFORMANCE ISSUES');
        $memoryPos = \strpos($result, '## MEMORY ISSUES');
        $otherPos = \strpos($result, '## OTHER TRANSACTIONS');

        $this->assertLessThan($performancePos, $criticalPos);
        $this->assertLessThan($memoryPos, $performancePos);
        $this->assertLessThan($otherPos, $memoryPos);
    }

    public function testNumericFormattingInReports(): void
    {
        $transaction = $this->createSampleTransaction([
            'duration' => 1234.5678,
            'memory_peak' => 67.8901
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        $this->assertStringContainsString('1,234.57ms', $result);
        $this->assertStringContainsString('67.89MB', $result);
    }

    public function testMultipleCriticalTransactions(): void
    {
        $transactions = [
            $this->createSampleTransaction([
                'name' => 'FirstFailedTransaction',
                'result' => 'failed',
                'hash' => 'hash1'
            ]),
            $this->createSampleTransaction([
                'name' => 'SecondFailedTransaction',
                'result' => 'error',
                'hash' => 'hash2'
            ])
        ];

        $report = new WorstTransactionsReport($transactions);
        $result = $report->generate();

        $this->assertStringContainsString('### CRITICAL: FirstFailedTransaction', $result);
        $this->assertStringContainsString('### CRITICAL: SecondFailedTransaction', $result);
        $this->assertStringContainsString('Use hash `hash1`', $result);
        $this->assertStringContainsString('Use hash `hash2`', $result);
    }

    public function testComplexTransactionCategorization(): void
    {
        // Transaction that is both slow AND memory intensive but not critical
        $transaction = $this->createSampleTransaction([
            'duration' => 2000.0, // Slow
            'memory_peak' => 75.0, // Memory intensive
            'result' => 'success' // Not critical
        ]);

        $report = new WorstTransactionsReport([$transaction]);
        $result = $report->generate();

        // Should be categorized as slow (first condition that matches)
        $this->assertStringContainsString('## PERFORMANCE ISSUES', $result);
        $this->assertStringNotContainsString('## MEMORY ISSUES', $result);
        $this->assertStringNotContainsString('## CRITICAL ISSUES', $result);
    }
}
