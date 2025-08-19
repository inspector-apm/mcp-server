<?php

declare(strict_types=1);

namespace Inspector\MCPServer;

class App
{
    public function __construct(
        public readonly string $name,
        public readonly string $language,
        public readonly string $platform,
    ) {
    }

    public function description(): string
    {
        return "*{$this->name}* is a {$this->language} application built with {$this->platform}.";
    }
}
