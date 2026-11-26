<?php

namespace Whilesmart\McpTokens\Concerns;

use Laravel\Mcp\Request;
use ReflectionClass;
use Whilesmart\McpTokens\Attributes\RequiresScope;
use Whilesmart\McpTokens\McpTokens;
use Whilesmart\McpTokens\Models\McpToken;

trait InteractsWithMcpToken
{
    /**
     * laravel/mcp drops a tool that returns false here from tools/list and answers tools/call with
     * "not found", so a token without the #[RequiresScope] scope neither sees nor runs the tool.
     */
    public function shouldRegister(Request $request): bool
    {
        $attribute = (new ReflectionClass($this))->getAttributes(RequiresScope::class)[0] ?? null;
        if (! $attribute) {
            return true;
        }
        $token = $this->mcpToken($request);

        return $token ? $token->allows($attribute->newInstance()->scope) : ! config('mcp-tokens.server.tokens_only', true);
    }

    protected function mcpToken(Request $request): ?McpToken
    {
        return app(McpTokens::class)->current($request->user());
    }

    protected function tokenAllows(Request $request, string $scope): bool
    {
        return (bool) $this->mcpToken($request)?->allows($scope);
    }

    protected function recordActivity(Request $request, string $action, string|int|null $subjectId = null): void
    {
        if ($token = $this->mcpToken($request)) {
            app(McpTokens::class)->record($token, $action, $subjectId);
        }
    }
}
