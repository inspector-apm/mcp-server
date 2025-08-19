# Inspector MCP Server

AI-powered debugging and monitoring integration for PHP applications through the Model Context Protocol.

## Overview

The Inspector MCP Server bridges the gap between AI coding assistants and production application monitoring,
enabling developers to leverage AI for faster debugging and improved application reliability. Built specifically
for the PHP ecosystem, this server integrates seamlessly with Inspector.dev to provide AI agents with real-time
access to production error data and intelligent analysis.

## Install

```
composer require inspector-apm/mcp-server --dev
```

## Client Configuration

The configuration below is for the MCP client like coding agents (Claude Code, Gemini Code Assist, ), or agentic IDEs like Jetbrains, Windsurf, Cursor, etc.

```json
{
    "mcpServers": {
        "inspector": {
            "command": "php",
            "args": ["[absolute-path-to-your-vendor-folder]/inspector-apm/mcp-server/server.php"],
            "env": {
                "INSPECTOR_API_KEY": "xxxx",
                "INSPECTOR_APP_ID": "xxxx"
            }
        }
    }
}
```

You can get your application MCP configuration from App Settings in your [Inspector dashboard](https://app.inspector.dev).

Follow this link to create a new API key: [Inspector API KEY](https://app.inspector.dev/account/api).

## What It Does

Transform your AI coding assistant into a production-aware debugging partner:

- **🔍 Smart Error Analysis** - AI agents can fetch and analyze recent production errors with comprehensive context
- **📊 Intelligent Prioritization** - Automatic error classification by frequency, severity, and recency
- **💡 Actionable Recommendations** - AI-powered suggestions for root cause analysis and resolution strategies
- **🎯 Code-Level Insights** - Direct integration with stack traces and application source code locations
- **⚡ Developer Experience** - Optimized for modern PHP development workflows, including Neuron AI framework

## Key Features

### Production Error Monitoring
- Retrieve errors from the last 24 hours with detailed context
- Frequency analysis and trend detection
- Real-time error status and recency indicators

### AI-Optimized Reporting
- LLM-friendly error formatting for maximum comprehension
- Pattern detection for recurring issues
- Automated categorization of error types (API issues, database problems, performance bottlenecks)

### Developer-Centric Design
- Framework-agnostic PHP integration
- Comprehensive stack trace analysis
- Direct code location references for faster debugging

## Perfect For

- **PHP Developers** building modern applications who want AI-assisted debugging
- **Teams using Inspector.dev** for production monitoring
- **[Neuron AI Framework](https://neuron-ai.dev)** users leveraging AI agents in their development workflow
- **DevOps Engineers** seeking intelligent error analysis and resolution guidance

## About

This MCP server is developed by the team behind [Inspector.dev](https://inspector.dev)
and the [Neuron AI framework](https://neuron.dev), bringing enterprise-grade monitoring capabilities to
AI-powered applications.
