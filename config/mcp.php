<?php

use EvolutionCMS\eMCP\Servers\ContentServer;

return [
    'redirect_domains' => ['*'],

    'servers' => [
        [
            'handle' => 'content',
            'transport' => 'web',
            'route' => '/mcp/content',
            'class' => ContentServer::class,
            'enabled' => true,
            'auth' => 'sapi_jwt',
            'scopes' => ['mcp:read', 'mcp:call'],
            'scope_map' => [
                'mcp:read' => ['initialize', 'ping', 'tools/list', 'resources/list', 'resources/read', 'resources/templates/list', 'prompts/list', 'prompts/get', 'notifications/*'],
                'mcp:call' => ['tools/call'],
            ],
            'limits' => [
                'max_payload_kb' => 128,
                'max_result_items' => 50,
            ],
            'rate_limit' => [
                'per_minute' => 30,
            ],
            'security' => [
                'deny_tools' => [],
            ],
            // Tool classes from other packages to expose on this server (alternative to
            // registering an EvolutionCMS\eMCP\Contracts\ToolProvider at runtime).
            'extra_tools' => [],
        ],
        [
            'handle' => 'content-local',
            'transport' => 'local',
            'class' => ContentServer::class,
            // Disabled by default to avoid duplicate tool-name registration conflict with "content" web server.
            'enabled' => false,
        ],
    ],
];
