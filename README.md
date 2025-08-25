# Inspector MCP Server

Inspector MCP Server was built specifically for the PHP ecosystem; this server provides tools to allow
AI coding agents to access production errors data with comprehensive context, to automatically fix issues,
and to provide actionable recommendations for root cause analysis and resolution strategies.

## HTTP Streaming Transport (connect to our remote instance)

The HTTP Streaming Transport is the recommended way to connect to the MCP server. You don't need to install anything in your machine.
Here is the configuration for the MCP client:

```json
{
    "mcpServers": {
        "inspector": {
            "url": "https://mcp.inspector.dev"
        }
    }
}
```

## STDIO Transport (install in your local machine)

### Install

You can install the MCP server as a dev-dependency in your project:

```
composer require inspector-apm/mcp-server --dev
```

### Client Configuration

The configuration below is for the MCP client like coding agents (Claude Code, Gemini Code Assist, etc), or agentic IDEs like Jetbrains, Windsurf, Cursor, etc.

```json
{
    "mcpServers": {
        "inspector": {
            "command": "php",
            "args": [
                "absolute-path-to-your-app-vendor-folder/inspector-apm/mcp-server/server.php"
            ],
            "env": {
                "INSPECTOR_API_KEY": "xxxx", // Inspector API key (https://app.inspector.dev/account/api)
                "INSPECTOR_APP_ID": "xxxx" // The App ID on the Inspector dashboard
            }
        }
    }
}
```

You need three information to complete this configuration:

- **Absolute path of the vendor folder**: This is the root path where the vendor folder of your project is located in your computer. The next part is the path to point to the file that runs the MCP server (inspector-apm/mcp-server/server.php)
- **INSPECTOR_API_KEY**: [Click Here](https://app.inspector.dev/account/api) to generate a new API key
- **INSPECTOR_APP_ID**: This is the unique identifier of your application inside Inspector. You can get this information in the Application Settings menu in the Inspector dashboatrd

You can get the MCP configuration for your application by navigating to the *Application Settings* section in the [Inspector dashboard](https://app.inspector.dev).

### Claude Code Configuration

Once you have the information above you can connect the Inspector MCP server to Claude Code with the command below:

```
claude mcp add inspector --env INSPECTOR_API_KEY=YOUR_KEY --env INSPECTOR_APP_ID=YOUR_APP_ID -- php absolute_path_to_your_app_vendor_folder/inspector-apm/mcp-server/server.php
```

For other agents check out their documentation on how to connect to local (STDIO) MCP servers.

## Available Tools

| Name     |  Description  |           Properties |
|----------|:-------------:|---------------------:|
| get_production_errors | Get recent production errors to debug and fix application issues. Returns a comprehensive analysis of errors, including frequency, severity, affected code locations, and AI-powered recommendations for resolution. Use this tool when investigating application problems, performance issues, or when you need to understand what's currently broken in production. Essential for proactive debugging and maintaining application reliability.  |         (int) $hours |
| get_error_analysis |   Get detailed error analysis from the production environment including the actual application source file (not just library stack traces), error patterns, code context, occurrence frequency, and structured debugging guidance to help you fix the issue quickly.    | (string) $group_hash |
| worst_performing_transactions | Retrieve the list of the ten worst performing transactions in the selected time range (24 hours by default). A transaction represents an execution cycle of the application. It could be an HTTP request, a background job, or a console command. |         (int) $hours |
| transaction_details | Retrieve the transaction details and the timeline of all tasks executed during the transaction. The timeline includes the start and duration of each task (database queries, cache commands, call to external http services, and so on). |         (int) $hours |

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
