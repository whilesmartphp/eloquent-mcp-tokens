<?php

use Whilesmart\McpTokens\Formatters\DefaultResponseFormatter;
use Whilesmart\McpTokens\Http\Controllers\McpTokenController;
use Whilesmart\McpTokens\Http\Requests\StoreMcpTokenRequest;
use Whilesmart\McpTokens\Http\Resources\McpTokenEventResource;
use Whilesmart\McpTokens\Http\Resources\McpTokenResource;
use Whilesmart\McpTokens\Models\McpToken;
use Whilesmart\McpTokens\Policies\ConfigLifetimePolicy;
use Whilesmart\McpTokens\Support\NullRequestScoper;

return [
    'register_routes' => env('MCP_TOKENS_REGISTER_ROUTES', true),
    'route_prefix' => env('MCP_TOKENS_ROUTE_PREFIX', 'api'),
    'route_middleware' => ['api', 'auth:sanctum'],
    'write_middleware' => [],
    'actions' => ['index', 'store', 'destroy', 'activity', 'current'],

    'tokens_table' => env('MCP_TOKENS_TABLE', 'mcp_tokens'),
    'events_table' => env('MCP_TOKEN_EVENTS_TABLE', 'mcp_token_events'),

    'model' => McpToken::class,
    'controller' => McpTokenController::class,
    'store_request' => StoreMcpTokenRequest::class,
    'resource' => McpTokenResource::class,
    'event_resource' => McpTokenEventResource::class,
    'response_formatter' => DefaultResponseFormatter::class,
    'lifetime_policy' => ConfigLifetimePolicy::class,
    'request_scoper' => NullRequestScoper::class,

    // Sanctum ability that marks a token as an MCP token. Granted scopes are added as "<ability>:<scope>".
    'ability' => 'mcp',

    // Abilities copied from the creating session onto the new token when that session holds them.
    'carry_abilities' => ['2fa-verified'],

    // Scopes a token may be granted. Tools check them; an empty list means tokens carry no scopes.
    'scopes' => [],

    'lifetime' => [
        'default_days' => (int) env('MCP_TOKENS_DEFAULT_DAYS', 30),
        'max_days' => (int) env('MCP_TOKENS_MAX_DAYS', 90),
        'presets' => [7, 30, 90],
    ],

    'restriction' => [
        'enabled' => env('MCP_TOKENS_RESTRICT', true),
        // "METHOD uri" => activity action recorded on success, or null to allow without recording.
        'allow' => [],
    ],

    'server' => [
        'enabled' => env('MCP_TOKENS_SERVER', false),
        'endpoint' => env('MCP_TOKENS_ENDPOINT', 'mcp'),
        'class' => null,
        'middleware' => ['auth:sanctum', 'throttle:60,1'],
        'tokens_only' => true,
    ],

    'owner_key_type' => 'int',
];
