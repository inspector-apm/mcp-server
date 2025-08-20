<?php

declare(strict_types=1);

namespace Inspector\MCPServer\Tools;

use GuzzleHttp\Exception\GuzzleException;
use Inspector\MCPServer\HttpClientUtils;
use Inspector\MCPServer\Reports\TransactionDetailReport;
use Inspector\MCPServer\Reports\WorstTransactionsReport;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class TransactionTools
{
    use HttpClientUtils;

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    #[McpTool(name: 'worst_performing_transactions', description: 'Retrieve the list of the ten worst performing transactions in the selected time range (24 hours by default). A transaction represents an execution cycle of the application. It could be an HTTP request, a background job, or a console command.')]
    public function worstTransactions(
        #[Schema(description: 'The number of hours to look back for transactions (24 by default).')]
        int $hours = 24,
    ): string {
        $this->setApp();

        $start = \date('Y-m-d H:i', \strtotime("-{$hours} hours"));

        $result = $this->httpClient()->post("worst-transactions?filter[start]={$start}")->getBody()->getContents();
        $result = \json_decode($result, true);

        return (string) new WorstTransactionsReport($result);
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    #[McpTool(name: 'transaction_details', description: 'Retrieve the transaction details and the timeline of all tasks executed during the transaction. The timeline includes the start and duration of each task (database queries, cache commands, call to external http services, and so on).')]
    public function transactionDetails(string $hash): string
    {
        $this->setApp();

        $transaction = $this->httpClient()->get("transactions/{$hash}/occurrence")->getBody()->getContents();
        $transaction = \json_decode($transaction, true);

        $timeline = $this->httpClient()->get("transactions/{$hash}/segments")->getBody()->getContents();
        $timeline = \json_decode($timeline, true);
        $timeline = \array_map(function (array $segment): array {
            if ($segment['type'] === 'exception') {
                $segment['app_file'] = $this->getAppFileFromStack($segment['context']['Error']['stack'] ?? []);
            }

            return $segment;
        }, $timeline);

        return (string) new TransactionDetailReport($transaction, $timeline);
    }
}
