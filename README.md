# Inspector MCP Server

## Install

```
composer require inspector-apm/mcp-server
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

