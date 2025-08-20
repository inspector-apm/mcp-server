# Inspector MCP Server

Inspector MCP Server was built specifically for the PHP ecosystem; this server provides tools to allow
AI coding agents to access production errors data with comprehensive context, to automatically fix issues,
and to provide actionable recommendations for root cause analysis and resolution strategies.

## Install

You can install the MCP server as a dev-dependency in your project:

```
composer require inspector-apm/mcp-server --dev
```

## Client Configuration

The configuration below is for the MCP client like coding agents (Claude Code, Gemini Code Assist, etc), or agentic IDEs like Jetbrains, Windsurf, Cursor, etc.

```json
{
    "mcpServers": {
        "inspector": {
            "command": "php",
            "args": ["absolute-path-to-your-app-vendor-folder/inspector-apm/mcp-server/server.php"],
            "env": {
                "INSPECTOR_API_KEY": "xxxx", // Inspector API key (https://app.inspector.dev/account/api)
                "INSPECTOR_APP_ID": "xxxx" // The App ID on the Inspector dashboard
            }
        }
    }
}
```

You can get the MCP configuration for your application by navigating to the *Application Settings* section in the [Inspector dashboard](https://app.inspector.dev).

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
