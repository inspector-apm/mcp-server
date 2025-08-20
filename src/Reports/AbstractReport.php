<?php

namespace Inspector\MCPServer\Reports;

abstract class AbstractReport implements \Stringable
{
    abstract public function generate(): string;

    public function __toString(): string
    {
        return $this->generate();
    }
}
