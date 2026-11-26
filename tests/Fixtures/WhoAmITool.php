<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Whilesmart\McpTokens\Concerns\InteractsWithMcpToken;

class WhoAmITool extends Tool
{
    use InteractsWithMcpToken;

    protected string $name = 'whoami';

    protected string $description = 'Says which owner the calling token belongs to.';

    public function handle(Request $request): Response
    {
        $token = $this->mcpToken($request);
        $this->recordActivity($request, 'whoami', $token?->owner_id);

        return Response::text($token ? "owner {$token->owner_id}, write ".($this->tokenAllows($request, 'write') ? 'yes' : 'no') : 'no mcp token');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
