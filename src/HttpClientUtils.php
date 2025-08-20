<?php

declare(strict_types=1);

namespace Inspector\MCPServer;

use GuzzleHttp\Client;

trait HttpClientUtils
{
    protected Client $client;

    protected App $app;

    protected function getAppFileFromStack(array $stacktrace): ?array
    {
        foreach ($stacktrace as $frame) {
            if ($frame['in_app']) {
                $frame['code'] = \array_reduce($frame['code'], fn ($carry, $item) => $carry.\PHP_EOL.$item['line'].' | '.$item['code'], '');
                return $frame;
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    protected function validateApiKey(): string
    {
        if (!$key = \getenv('INSPECTOR_API_KEY')) {
            throw new \Exception('API key not found');
        }

        return $key;
    }

    /**
     * @throws \Exception
     */
    protected function validateAppId(): string
    {
        if (!$id = \getenv('INSPECTOR_APP_ID')) {
            throw new \Exception('Inspector application ID not found');
        }

        return $id;
    }

    /**
     * @throws \Exception
     */
    protected function httpClient(): Client
    {
        $key = $this->validateApiKey();
        $id = $this->validateAppId();

        return $this->client ?? $this->client = new Client([
            'base_uri' => $this->normalizeUri("https://app.inspector.dev/api/apps/{$id}"),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$key}",
            ]
        ]);
    }

    protected function normalizeUri(string $uri): string
    {
        return \trim($uri, '/').'/';
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    protected function setApp(): App
    {
        $response = $this->httpClient()->get('')->getBody()->getContents();

        $app = \json_decode($response, true);

        $this->app = new App(
            $app['full_name'],
            $app['platform']['language'],
            $app['platform']['name'],
        );

        return $this->app;
    }
}
