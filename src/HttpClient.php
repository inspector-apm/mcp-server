<?php

declare(strict_types=1);

namespace Inspector\MCPServer;

use GuzzleHttp\Client;
use Inspector\MCPServer\App;

trait HttpClient
{
    protected Client $client;

    protected App $app;

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
    protected function validateAppId(): int
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
    protected function setApp(): void
    {
        $response = $this->httpClient()->get('')->getBody()->getContents();

        $app = \json_decode($response, true);

        $this->app = new App(
            $app['full_name'],
            $app['platform']['language'],
            $app['platform']['name'],
        );
    }
}
