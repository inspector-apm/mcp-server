<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tests;

use Inspector\MCPServer\Reports\TransactionDetailReport;
use PHPUnit\Framework\TestCase;

class TransactionDetailReportTest extends TestCase
{
    private function getSampleTransaction(): array
    {
        return [
            "timestamp" => "2025-08-19 07:00:10",
            "host" => [
                "hostname" => "307510.cloudwaysapps.com",
                "ip" => "127.0.1.1",
                "os" => "Linux"
            ],
            "type" => "request",
            "name" => "GET /api/vehicles/archive",
            "hash" => "e5ff47c040136344c07a07f03f4d51296542e99c9f28c372a75faec20e9c31b0",
            "duration" => 4174.63,
            "result" => "200",
            "memory_peak" => 5.92,
            "http" => [
                "request" => [
                    "method" => "GET"
                ],
                "url" => [
                    "protocol" => "https",
                    "port" => "80",
                    "path" => "/http.php",
                    "search" => "?",
                    "full" => "https://gestionale.rentincosta.it/api/vehicles/archive"
                ]
            ]
        ];
    }

    private function getSampleTasks(): array
    {
        return [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `vehicles` where LOWER(brand) LIKE ? and `engine_displacement` = ? and `vehicles`.`deleted_at` is null",
                "duration" => 2.64,
                "start" => 6.55
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `vehicles` where LOWER(brand) LIKE ? and `engine_displacement` = ? and `available` = ? and not exists (select * from `bookings` where `vehicles`.`id` = `bookings`.`vehicle_id` and ((`start_at` <= ? and `end_at` >= ?) or (`start_at` <= ? and `end_at` >= ?) or (`start_at` >= ? and `end_at` <= ?)) and `warranty_contract` = ? and `bookings`.`deleted_at` is null) and `vehicles`.`deleted_at` is null",
                "duration" => 150.03,
                "start" => 11.77
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "redis",
                "label" => "redis:get",
                "duration" => 0.29,
                "start" => 22.3
            ],
            [
                "timestamp" => "2025-08-20 10:37:06",
                "type" => "exception",
                "label" => "Trying to read property of null",
                "duration" => 3.01,
                "start" => 28.3,
                "app_file" => [
                    "file" => "/home/public_html/app/Jobs/JobRateLimiter.php",
                    "line" => 111,
                    "code" => "
                109 | ...
                110 | ...
                111 | ...
                112 | ...
                113 | ..."
                ]
            ]
        ];
    }

    private function getNPlusOneTasks(): array
    {
        return [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `users` where `id` = ?",
                "duration" => 5.0,
                "start" => 10.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `users` where `id` = ?",
                "duration" => 4.5,
                "start" => 15.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `users` where `id` = ?",
                "duration" => 6.0,
                "start" => 20.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `users` where `id` = ?",
                "duration" => 5.5,
                "start" => 25.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from `users` where `id` = ?",
                "duration" => 4.8,
                "start" => 30.0
            ]
        ];
    }

    public function testBasicReportGeneration(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Check that the main sections are present
        $this->assertStringContainsString('TRANSACTION ANALYSIS REPORT', $result);
        $this->assertStringContainsString('GET /api/vehicles/archive', $result);
        $this->assertStringContainsString('Duration: 4,174.63ms', $result);
        $this->assertStringContainsString('Memory Peak: 5.92MB', $result);
        $this->assertStringContainsString('Result: 200', $result);
    }

    public function testExceptionReporting(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        // Should contain exception section
        $this->assertStringContainsString('CRITICAL ISSUES - EXCEPTIONS FOUND', $result);
        $this->assertStringContainsString('Trying to read property of null', $result);
        $this->assertStringContainsString('/home/public_html/app/Jobs/JobRateLimiter.php', $result);
        $this->assertStringContainsString('Line: 111', $result);
        $this->assertStringContainsString('RECOMMENDATION: Address these exceptions immediately', $result);
    }

    public function testNoExceptionsHandling(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = \array_filter($this->getSampleTasks(), fn ($task) => $task['type'] !== 'exception');

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        // Should not contain exception section
        $this->assertStringNotContainsString('CRITICAL ISSUES - EXCEPTIONS FOUND', $result);
        $this->assertStringNotContainsString('Trying to read property of null', $result);
    }

    public function testPerformanceAnalysis(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('PERFORMANCE ANALYSIS', $result);
        $this->assertStringContainsString('Total Transaction Duration: 4,174.63ms', $result);
        $this->assertStringContainsString('WARNING: Transaction duration exceeds 1 second', $result);

        // Should detect slow tasks (>100ms)
        $this->assertStringContainsString('Slow Tasks (>100ms)', $result);
        $this->assertStringContainsString('mysql: 150.03ms', $result);
    }

    public function testFastTransactionPerformance(): void
    {
        $transaction = $this->getSampleTransaction();
        $transaction['duration'] = 250.0; // Fast transaction
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringNotContainsString('WARNING: Transaction duration exceeds 1 second', $result);
        $this->assertStringNotContainsString('NOTICE: Transaction duration is moderately slow', $result);
    }

    public function testModeratelySlowTransactionPerformance(): void
    {
        $transaction = $this->getSampleTransaction();
        $transaction['duration'] = 750.0; // Moderately slow transaction
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringNotContainsString('WARNING: Transaction duration exceeds 1 second', $result);
        $this->assertStringContainsString('NOTICE: Transaction duration is moderately slow', $result);
    }

    public function testDatabaseAnalysis(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('DATABASE ANALYSIS', $result);
        $this->assertStringContainsString('Query Count: 2', $result);
        $this->assertStringContainsString('SLOWEST DATABASE QUERIES', $result);

        // Should show database time percentage
        $this->assertMatchesRegularExpression('/Total Database Time: \d+\.\d+ms \(\d+\.\d+% of transaction\)/', $result);
    }

    public function testNoDatabaseTasks(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "redis",
                "label" => "redis:get",
                "duration" => 0.29,
                "start" => 22.3
            ]
        ];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('No database queries detected in this transaction', $result);
    }

    public function testNPlusOneDetection(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getNPlusOneTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('POTENTIAL N+1 QUERY ISSUES DETECTED', $result);
        $this->assertStringContainsString('Occurrences: 5 times', $result);
        $this->assertStringContainsString('Consider using eager loading or batch queries', $result);
    }

    public function testTimelineSummary(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('EXECUTION TIMELINE SUMMARY', $result);
        $this->assertStringContainsString('Mysql: 2 operations', $result);
        $this->assertStringContainsString('Redis: 1 operations', $result);
        $this->assertStringContainsString('Exception: 1 operations', $result);

        // Should contain investigation recommendations
        $this->assertStringContainsString('For detailed investigation', $result);
        $this->assertStringContainsString('Check route definition for: /http.php', $result);
    }

    public function testEmptyTasks(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('TRANSACTION ANALYSIS REPORT', $result);
        $this->assertStringContainsString('No database queries detected', $result);
    }

    public function testNonRequestTransaction(): void
    {
        $transaction = $this->getSampleTransaction();
        $transaction['type'] = 'job';
        $transaction['name'] = 'ProcessPayments';
        unset($transaction['http']);

        $tasks = $this->getSampleTasks();

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('JOB ProcessPayments', $result);
        $this->assertStringNotContainsString('URL:', $result);
        $this->assertStringNotContainsString('Check route definition for:', $result);
    }

    public function testSlowTaskThreshold(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "fast query",
                "duration" => 50.0,
                "start" => 10.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "slow query",
                "duration" => 150.0,
                "start" => 60.0
            ]
        ];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('Slow Tasks (>100ms)', $result);
        $this->assertStringContainsString('slow query', $result);
    }

    public function testQueryNormalization(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "SELECT * FROM users WHERE id = ? AND status = ?",
                "duration" => 5.0,
                "start" => 10.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "select * from users where id = ? and status = ?",
                "duration" => 4.5,
                "start" => 15.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "SELECT * FROM users WHERE id = ? AND status = ?",
                "duration" => 6.0,
                "start" => 20.0
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => "SELECT * FROM users WHERE id = ? AND status = ?",
                "duration" => 5.5,
                "start" => 25.0
            ]
        ];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('POTENTIAL N+1 QUERY ISSUES DETECTED', $result);
        $this->assertStringContainsString('Occurrences: 4 times', $result);
    }

    public function testStringTruncation(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "mysql",
                "label" => \str_repeat("very long query with lots of text ", 10),
                "duration" => 150.0,
                "start" => 10.0
            ]
        ];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        // Should contain truncated query with ellipsis
        $this->assertStringContainsString('...', $result);
    }

    public function testMultipleExceptions(): void
    {
        $transaction = $this->getSampleTransaction();
        $tasks = [
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "exception",
                "label" => "First exception",
                "duration" => 3.0,
                "start" => 10.0,
                "app_file" => [
                    "file" => "/path/to/file1.php",
                    "line" => 42
                ]
            ],
            [
                "timestamp" => "2025-08-20 10:37:05",
                "type" => "exception",
                "label" => "Second exception",
                "duration" => 2.5,
                "start" => 20.0,
                "app_file" => [
                    "file" => "/path/to/file2.php",
                    "line" => 84
                ]
            ]
        ];

        $report = new TransactionDetailReport($transaction, $tasks);
        $result = $report->generate();

        $this->assertStringContainsString('Exception #1:', $result);
        $this->assertStringContainsString('Exception #2:', $result);
        $this->assertStringContainsString('First exception', $result);
        $this->assertStringContainsString('Second exception', $result);
        $this->assertStringContainsString('/path/to/file1.php', $result);
        $this->assertStringContainsString('/path/to/file2.php', $result);
    }
}
