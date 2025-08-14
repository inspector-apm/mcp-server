<?php

namespace Inspector\MCPServer;

use GuzzleHttp\Client;

trait HttpClient
{
    protected Client $client;

    protected function httpClient(): Client
    {
        return $this->client ?? $this->client = new Client([
            'base_uri' => 'https://app.inspector.dev/api/',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $_ENV['INSPECTOR_API_KEY'],
            ]
        ]);
    }
}
