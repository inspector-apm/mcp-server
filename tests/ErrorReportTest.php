<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tests;

use Inspector\MCPServer\Reports\ErrorReport;
use PHPUnit\Framework\TestCase;

class ErrorReportTest extends TestCase
{
    public function testBasicErrorReportGeneration(): void
    {
        $errorData = [
            'message' => 'Undefined variable $user',
            'class' => 'Error',
            'hash' => 'abc123',
            'nth' => '1',
            'file' => '/vendor/framework/core.php',
            'line' => 42
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('ERROR SUMMARY', $output);
        $this->assertStringContainsString('Type: Error', $output);
        $this->assertStringContainsString('Message: Undefined variable $user', $output);
        $this->assertStringContainsString('Error Hash: abc123', $output);
        $this->assertStringContainsString('Occurrence Count: 1 time(s)', $output);
    }

    public function testStringableImplementation(): void
    {
        $errorData = [
            'message' => 'Test error',
            'class' => 'Exception'
        ];

        $report = new ErrorReport($errorData);
        $stringOutput = (string) $report;
        $generateOutput = $report->generate();

        $this->assertEquals($generateOutput, $stringOutput);
    }

    public function testErrorContextWithAppFile(): void
    {
        $errorData = [
            'message' => 'Call to a member function getName() on null',
            'class' => 'Error',
            'file' => '/vendor/framework/core.php',
            'line' => 42,
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 15,
                'code' => '$name = $user->getName();'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('ERROR CONTEXT', $output);
        $this->assertStringContainsString('Stack Trace Origin:', $output);
        $this->assertStringContainsString('File: /vendor/framework/core.php', $output);
        $this->assertStringContainsString('Line: 42', $output);
        $this->assertStringContainsString('APPLICATION SOURCE', $output);
        $this->assertStringContainsString('File: /app/User.php', $output);
        $this->assertStringContainsString('Line: 15', $output);
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString('$name = $user->getName();', $output);
    }

    public function testCodeAnalysisForNullPropertyAccess(): void
    {
        $errorData = [
            'message' => 'Attempt to read property "name" on null',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 10,
                'code' => 'echo $user->name;'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('CODE ANALYSIS', $output);
        $this->assertStringContainsString('Error Pattern: NULL PROPERTY ACCESS', $output);
        $this->assertStringContainsString('Issue: Attempting to access a property on a null object', $output);
        $this->assertStringContainsString('Focus Line: 10', $output);
        $this->assertStringContainsString('Investigation Priority: Check for null values', $output);
        $this->assertStringContainsString('Problematic Property: name', $output);
    }

    public function testCodeAnalysisForNullMethodCall(): void
    {
        $errorData = [
            'message' => 'Call to a member function getName() on null',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 20,
                'code' => '$name = $user->getName();'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Error Pattern: NULL METHOD CALL', $output);
        $this->assertStringContainsString('Issue: Attempting to call a method on a null object', $output);
        $this->assertStringContainsString('Focus Line: 20', $output);
        $this->assertStringContainsString('Verify object instantiation', $output);
        $this->assertStringContainsString('Problematic Method: getName()', $output);
    }

    public function testCodeAnalysisForUndefinedArrayKey(): void
    {
        $errorData = [
            'message' => 'Undefined array key "email"',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 30,
                'code' => 'echo $data["email"];'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Error Pattern: UNDEFINED ARRAY ACCESS', $output);
        $this->assertStringContainsString('Issue: Accessing an array key that does not exist', $output);
        $this->assertStringContainsString('Focus Line: 30', $output);
        $this->assertStringContainsString('Validate array keys before access', $output);
        $this->assertStringContainsString('Missing Array Key: email', $output);
    }

    public function testCodeAnalysisForUndefinedIndex(): void
    {
        $errorData = [
            'message' => 'Undefined index: username',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 25,
                'code' => 'echo $_POST["username"];'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Error Pattern: UNDEFINED ARRAY ACCESS', $output);
    }

    public function testExistingFixSection(): void
    {
        $errorData = [
            'message' => 'Test error',
            'fix' => [
                'platform' => 'Laravel',
                'language' => 'PHP',
                'proposal' => 'Add null check before accessing the property: if ($user !== null) { ... }'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('INSPECTOR AI ANALYSIS', $output);
        $this->assertStringContainsString('Detected Platform: Laravel', $output);
        $this->assertStringContainsString('Language: PHP', $output);
        $this->assertStringContainsString('Add null check before accessing', $output);
        $this->assertStringContainsString('This fix was generated by Inspector\'s AI', $output);
    }

    public function testActionableInsightsWithRecurringError(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '5',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 42
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('ACTIONABLE INSIGHTS', $output);
        $this->assertStringContainsString('This is a recurring error (5 occurrences)', $output);
        $this->assertStringContainsString('Debugging Strategy:', $output);
        $this->assertStringContainsString('Focus investigation on the APPLICATION SOURCE', $output);
        $this->assertStringContainsString('Open file: /app/User.php', $output);
        $this->assertStringContainsString('Navigate to line: 42', $output);
    }

    public function testSeverityDeterminationCritical(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '15'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Severity: CRITICAL (High frequency)', $output);
    }

    public function testSeverityDeterminationHigh(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '7'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Severity: HIGH (Multiple occurrences)', $output);
    }

    public function testSeverityDeterminationCriticalFatal(): void
    {
        $errorData = [
            'message' => 'Fatal error: Out of memory',
            'nth' => '1'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Severity: CRITICAL (Fatal)', $output);
    }

    public function testSeverityDeterminationMedium(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '2'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Severity: MEDIUM', $output);
    }

    public function testErrorPatternAnalysisRapidSuccession(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '10',
            'created_at' => '2024-01-01 10:00:00',
            'last_seen_at' => '2024-01-01 10:30:00'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Pattern: Rapid succession', $output);
    }

    public function testErrorPatternAnalysisIntermittent(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '3',
            'created_at' => '2024-01-01 10:00:00',
            'last_seen_at' => '2024-01-03 15:00:00'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Pattern: Intermittent over time', $output);
    }

    public function testErrorPatternAnalysisRecurring(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '3'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Pattern: Recurring', $output);
    }

    public function testErrorPatternAnalysisSingleOccurrence(): void
    {
        $errorData = [
            'message' => 'Test error',
            'nth' => '1'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Pattern: Single occurrence', $output);
    }

    public function testMinimalErrorData(): void
    {
        $errorData = [];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Type: Unknown', $output);
        $this->assertStringContainsString('Message: Unknown error', $output);
        $this->assertStringContainsString('Error Hash: N/A', $output);
        $this->assertStringContainsString('Occurrence Count: 1 time(s)', $output);
        $this->assertStringContainsString('Severity: MEDIUM', $output);
    }

    public function testNoAppFileCodeSection(): void
    {
        $errorData = [
            'message' => 'Test error',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 42
                // No 'code' key
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringNotContainsString('CODE ANALYSIS', $output);
        $this->assertStringNotContainsString('```php', $output);
    }

    public function testNoFixSection(): void
    {
        $errorData = [
            'message' => 'Test error'
            // No 'fix' key
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringNotContainsString('INSPECTOR AI ANALYSIS', $output);
    }

    public function testVariableExtractionFromComplexMessages(): void
    {
        $errorData = [
            'message' => 'Attempt to read property "email" on null in UserService',
            'app_file' => [
                'file' => '/app/User.php',
                'line' => 10,
                'code' => 'echo $user->email;'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString('Problematic Property: email', $output);
    }

    public function testCompleteErrorReport(): void
    {
        $errorData = [
            'message' => 'Call to a member function getName() on null',
            'class' => 'Error',
            'hash' => 'def456',
            'nth' => '3',
            'file' => '/vendor/framework/core.php',
            'line' => 100,
            'app_file' => [
                'file' => '/app/Services/UserService.php',
                'line' => 25,
                'code' => 'return $user->getName();'
            ],
            'fix' => [
                'platform' => 'Laravel',
                'language' => 'PHP',
                'proposal' => 'Check if $user is not null before calling getName() method'
            ],
            'created_at' => '2024-01-01 10:00:00',
            'last_seen_at' => '2024-01-01 12:00:00'
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        // Verify all sections are present
        $this->assertStringContainsString('ERROR SUMMARY', $output);
        $this->assertStringContainsString('ERROR CONTEXT', $output);
        $this->assertStringContainsString('CODE ANALYSIS', $output);
        $this->assertStringContainsString('INSPECTOR AI ANALYSIS', $output);
        $this->assertStringContainsString('ACTIONABLE INSIGHTS', $output);

        // Verify specific content
        $this->assertStringContainsString('Type: Error', $output);
        $this->assertStringContainsString('Occurrence Count: 3 time(s)', $output);
        $this->assertStringContainsString('Error Pattern: NULL METHOD CALL', $output);
        $this->assertStringContainsString('Problematic Method: getName()', $output);
        $this->assertStringContainsString('This is a recurring error (3 occurrences)', $output);
    }

    /**
     * @dataProvider errorMessageProvider
     */
    public function testDifferentErrorMessages(string $message, string $expectedPattern): void
    {
        $errorData = [
            'message' => $message,
            'app_file' => [
                'file' => '/app/Test.php',
                'line' => 1,
                'code' => 'test code'
            ]
        ];

        $report = new ErrorReport($errorData);
        $output = $report->generate();

        $this->assertStringContainsString($expectedPattern, $output);
    }

    public function errorMessageProvider(): array
    {
        return [
            ['Attempt to read property "name" on null', 'Error Pattern: NULL PROPERTY ACCESS'],
            ['Call to a member function save() on null', 'Error Pattern: NULL METHOD CALL'],
            ['Undefined array key "id"', 'Error Pattern: UNDEFINED ARRAY ACCESS'],
            ['Undefined index: username', 'Error Pattern: UNDEFINED ARRAY ACCESS'],
            ['Some other error message', 'CODE ANALYSIS'], // Should still show the analysis section
        ];
    }
}
