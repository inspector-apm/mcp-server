<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tests;

use Inspector\MCPServer\App;
use Inspector\MCPServer\Reports\ErrorsListReport;
use PHPUnit\Framework\TestCase;

class ErrorsListReportTest extends TestCase
{
    private App $app;
    private array $sampleErrors;
    private array $emptyErrors;
    private array $singleError;
    private array $highFrequencyErrors;
    private array $recentErrors;
    private array $apiErrors;

    protected function setUp(): void
    {
        $this->app = new App('Test app', 'php', 'Laravel');

        $this->emptyErrors = [];

        $this->singleError = [
            [
                'message' => 'Division by zero',
                'class' => 'DivisionByZeroError',
                'file' => '/app/Calculator.php',
                'line' => 45,
                'created_at' => '2024-08-18 10:00:00',
                'last_seen_at' => '2024-08-18 15:30:00',
                'nth' => '3',
                'group_hash' => 'abc123',
                'app_file' => [
                    'file' => '/app/Calculator.php',
                    'line' => 45,
                    'code' => "43 | public function divide(\$a, \$b) {\n44 |     // TODO: Add validation\n45 |     return \$a / \$b;\n46 | }\n47 |"
                ]
            ]
        ];

        $this->sampleErrors = [
            [
                'message' => 'Client error: `POST https://api.openai.com/v1/chat/completions` resulted in a `400 Bad Request` response',
                'class' => 'GuzzleHttp\Exception\ClientException',
                'file' => '/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php',
                'line' => 111,
                'created_at' => '2024-08-18 08:00:00',
                'last_seen_at' => '2024-08-19 16:30:00', // Recent
                'nth' => '25', // High frequency
                'group_hash' => 'xyz789',
                'app_file' => [
                    'file' => '/app/AI/ChatService.php',
                    'line' => 67,
                    'code' => "65 | \$response = \$this->client->post('chat/completions', [\n66 |     'json' => \$payload\n67 | ]);\n68 |"
                ]
            ],
            [
                'message' => 'Connection timeout',
                'class' => 'GuzzleHttp\Exception\ConnectException',
                'file' => '/vendor/guzzlehttp/guzzle/src/Handler/CurlHandler.php',
                'line' => 234,
                'created_at' => '2024-08-18 12:00:00',
                'last_seen_at' => '2024-08-18 14:00:00', // Older
                'nth' => '5', // Medium frequency
                'group_hash' => 'def456',
                'app_file' => [
                    'file' => '/app/Services/ExternalApiService.php',
                    'line' => 89,
                    'code' => "87 | try {\n88 |     \$response = \$this->httpClient->get(\$url);\n89 |     return \$response->getBody();\n90 | } catch (Exception \$e) {\n91 |"
                ]
            ],
            [
                'message' => 'SQLSTATE[42S02]: Base table or view not found',
                'class' => 'PDOException',
                'file' => '/app/Database/Connection.php',
                'line' => 156,
                'created_at' => '2024-08-19 09:00:00',
                'last_seen_at' => '2024-08-19 09:15:00',
                'nth' => '2', // Low frequency
                'group_hash' => 'ghi789'
            ]
        ];

        $this->highFrequencyErrors = [
            [
                'message' => 'Memory limit exceeded',
                'class' => 'Error',
                'file' => '/app/DataProcessor.php',
                'line' => 123,
                'created_at' => '2024-08-18 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '75', // Critical frequency
                'group_hash' => 'critical123'
            ]
        ];

        $this->recentErrors = [
            [
                'message' => 'Service unavailable',
                'class' => 'RuntimeException',
                'file' => '/app/Services/PaymentService.php',
                'line' => 45,
                'created_at' => '2024-08-19 16:45:00',
                'last_seen_at' => \date('Y-m-d H:i:s', \time() - 120), // 2 minutes ago
                'nth' => '1',
                'group_hash' => 'recent456'
            ]
        ];

        $this->apiErrors = [
            [
                'message' => 'API key invalid',
                'class' => 'GuzzleHttp\Exception\ClientException',
                'file' => '/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php',
                'line' => 111,
                'created_at' => '2024-08-19 14:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '8',
                'group_hash' => 'api123'
            ],
            [
                'message' => 'Rate limit exceeded',
                'class' => 'GuzzleHttp\Exception\ClientException',
                'file' => '/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php',
                'line' => 111,
                'created_at' => '2024-08-19 13:00:00',
                'last_seen_at' => '2024-08-19 16:00:00',
                'nth' => '15',
                'group_hash' => 'api456'
            ]
        ];
    }

    public function testGenerateReportWithEmptyErrors(): void
    {
        $report = (new ErrorsListReport($this->app, $this->emptyErrors))->generate();

        $this->assertStringContainsString('No errors detected in the last 24 hours', $report);
    }

    public function testGenerateReportWithSingleError(): void
    {
        $report = (new ErrorsListReport($this->app, $this->singleError))->generate();

        // Check header information
        $this->assertStringContainsString('Application Errors Report - Last 24 hours', $report);
        $this->assertStringContainsString('**Total Error Types:** 1', $report);
        $this->assertStringContainsString('**Total Occurrences:** 3', $report);

        // Check error details
        $this->assertStringContainsString('DivisionByZeroError', $report);
        $this->assertStringContainsString('Division by zero', $report);
        $this->assertStringContainsString('abc123', $report);
        $this->assertStringContainsString('/app/Calculator.php:45', $report);

        // Check application code is included
        $this->assertStringContainsString('return $a / $b;', $report);
    }

    public function testGenerateReportWithMultipleErrors(): void
    {
        $report = (new ErrorsListReport($this->app, $this->sampleErrors))->generate();

        // Check header statistics
        $this->assertStringContainsString('**Total Error Types:** 3', $report);
        $this->assertStringContainsString('**Total Occurrences:** 32', $report); // 25 + 5 + 2

        // Check all error classes are mentioned
        $this->assertStringContainsString('GuzzleHttp\Exception\ClientException', $report);
        $this->assertStringContainsString('GuzzleHttp\Exception\ConnectException', $report);
        $this->assertStringContainsString('PDOException', $report);

        // Check all group hashes are present
        $this->assertStringContainsString('xyz789', $report);
        $this->assertStringContainsString('def456', $report);
        $this->assertStringContainsString('ghi789', $report);
    }

    public function testExecutiveSummaryWithHighFrequencyErrors(): void
    {
        $report = (new ErrorsListReport($this->app, $this->highFrequencyErrors))->generate();

        $this->assertStringContainsString('HIGH PRIORITY', $report);
        $this->assertStringContainsString('1 error type(s) with 10+ occurrences', $report);
    }

    public function testExecutiveSummaryWithRecentErrors(): void
    {
        $report = (new ErrorsListReport($this->app, $this->recentErrors))->generate();

        $this->assertStringContainsString('ACTIVE', $report);
        $this->assertStringContainsString('1 error type(s) occurred in the last hour', $report);
    }

    public function testCriticalErrorsPrioritization(): void
    {
        $mixedErrors = \array_merge($this->sampleErrors, $this->highFrequencyErrors);
        $report = (new ErrorsListReport($this->app, $mixedErrors))->generate();

        // High frequency error should be listed first
        $memoryErrorPos = \strpos($report, 'Memory limit exceeded');
        $openaiErrorPos = \strpos($report, 'api.openai.com');

        $this->assertNotFalse($memoryErrorPos);
        $this->assertNotFalse($openaiErrorPos);
        $this->assertLessThan($openaiErrorPos, $memoryErrorPos);
    }

    public function testFrequencyLevelClassification(): void
    {
        // Test critical frequency (50+)
        $criticalError = [
            [
                'message' => 'Test critical error',
                'class' => 'Error',
                'file' => '/test.php',
                'line' => 1,
                'created_at' => '2024-08-19 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '75',
                'group_hash' => 'critical'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $criticalError))->generate();
        $this->assertStringContainsString('CRITICAL', $report);

        // Test high frequency (10-49)
        $highError = [
            [
                'message' => 'Test high error',
                'class' => 'Error',
                'file' => '/test.php',
                'line' => 1,
                'created_at' => '2024-08-19 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '25',
                'group_hash' => 'high'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $highError))->generate();
        $this->assertStringContainsString('HIGH', $report);

        // Test medium frequency (5-9)
        $mediumError = [
            [
                'message' => 'Test medium error',
                'class' => 'Error',
                'file' => '/test.php',
                'line' => 1,
                'created_at' => '2024-08-19 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '7',
                'group_hash' => 'medium'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $mediumError))->generate();
        $this->assertStringContainsString('MEDIUM', $report);

        // Test low frequency (<5)
        $lowError = [
            [
                'message' => 'Test low error',
                'class' => 'Error',
                'file' => '/test.php',
                'line' => 1,
                'created_at' => '2024-08-19 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '2',
                'group_hash' => 'low'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $lowError))->generate();
        $this->assertStringContainsString('LOW', $report);
    }

    public function testRecencyIndicators(): void
    {
        // Test active error (< 5 minutes)
        $activeError = [
            [
                'message' => 'Active error',
                'class' => 'Error',
                'file' => '/test.php',
                'line' => 1,
                'created_at' => \date('Y-m-d H:i:s'),
                'last_seen_at' => \date('Y-m-d H:i:s', \time() - 60), // 1 minute ago
                'nth' => '1',
                'group_hash' => 'active'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $activeError))->generate();
        $this->assertStringContainsString('ACTIVE', $report);
    }

    public function testErrorBreakdownGrouping(): void
    {
        $report = (new ErrorsListReport($this->app, $this->sampleErrors))->generate();

        // Check that errors are grouped by class
        $this->assertStringContainsString('### `GuzzleHttp\Exception\ClientException`', $report);
        $this->assertStringContainsString('### `GuzzleHttp\Exception\ConnectException`', $report);
        $this->assertStringContainsString('### `PDOException`', $report);

        // Check that occurrences are summed correctly for ClientException
        $this->assertStringContainsString('(25 total occurrences)', $report);
    }

    public function testReportStructure(): void
    {
        $report = (new ErrorsListReport($this->app, $this->sampleErrors))->generate();

        // Check all main sections are present
        $this->assertStringContainsString('# Application Errors Report - Last 24 hours', $report);
        $this->assertStringContainsString('## Executive Summary', $report);
        $this->assertStringContainsString('## Critical Errors', $report);
        $this->assertStringContainsString('## Complete Error Breakdown', $report);
        $this->assertStringContainsString('## AI Analysis & Recommendations', $report);
        $this->assertStringContainsString('### General Debugging Strategy', $report);

        // Check footer is present
        $this->assertStringContainsString('Inspector MCP Server', $report);
        $this->assertStringContainsString('group_hash values', $report);
    }

    public function testCodeSnippetInclusion(): void
    {
        $report = (new ErrorsListReport($this->app, $this->singleError))->generate();

        // Check that application code is properly formatted
        $this->assertStringContainsString('```php', $report);
        $this->assertStringContainsString('// File: /app/Calculator.php:45', $report);
        $this->assertStringContainsString('return $a / $b;', $report);
        $this->assertStringContainsString('```', $report);
    }

    public function testErrorWithoutAppFile(): void
    {
        $errorWithoutAppFile = [
            [
                'message' => 'Error without app file',
                'class' => 'Error',
                'file' => '/vendor/package/file.php',
                'line' => 123,
                'created_at' => '2024-08-19 10:00:00',
                'last_seen_at' => '2024-08-19 15:00:00',
                'nth' => '1',
                'group_hash' => 'noapp'
            ]
        ];

        $report = (new ErrorsListReport($this->app, $errorWithoutAppFile))->generate();

        // Should still generate a valid report
        $this->assertStringContainsString('Error without app file', $report);
        $this->assertStringContainsString('noapp', $report);

        // Should not contain an application source section
        $this->assertStringNotContainsString('**Application Source:**', $report);
    }

    public function testReportTimestamp(): void
    {
        $report = (new ErrorsListReport($this->app, $this->singleError))->generate();

        // Check that the current timestamp is included
        $currentDate = \date('Y-m-d');
        $this->assertStringContainsString("**Generated:** {$currentDate}", $report);
    }
}
