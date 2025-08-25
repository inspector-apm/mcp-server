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
        #[Schema(description: 'The maximum number of transactions to return. Default null to return all errors.')]
        int $limit = 10
    ): string {
        $this->setApp();

        $start = \date('Y-m-d H:i', \strtotime("-{$hours} hours"));

        $transactions = $this->httpClient()->post("worst-transactions", [
            'query' => [
                'filter' => [
                    'start' => $start,
                ]
            ]
        ])->getBody()->getContents();

        $transactions = \json_decode($transactions, true);

        // Early return if there are too many errors in a single request
        if (\count($transactions) > $limit) {
            return "Current research for the last {$hours} hours retrieved more than {$limit} transactions. They could flood the context window. "
                ."You can try to narrow the search by setting a limit or using a shorter time window.";
        }

        return (string) new WorstTransactionsReport(\array_slice($transactions, 0, $limit));
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
