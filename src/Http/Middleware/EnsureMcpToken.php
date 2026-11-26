<?php

namespace Whilesmart\McpTokens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\McpTokens\Contracts\ResponseFormatter;
use Whilesmart\McpTokens\McpTokens;

class EnsureMcpToken
{
    public function __construct(private McpTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('mcp-tokens.server.tokens_only', true) && ! $this->tokens->current($request->user())) {
            return app(ResponseFormatter::class)->failure('Use an MCP token for this endpoint.', 403, ['code' => 'mcp_token_required']);
        }

        return $next($request);
    }
}
