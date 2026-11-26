<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Whilesmart\McpTokens\Attributes\RequiresScope;
use Whilesmart\McpTokens\Concerns\InteractsWithMcpToken;

#[RequiresScope('write')]
class ArchiveCaseTool extends Tool
{
    use InteractsWithMcpToken;

    protected string $name = 'archive-case';

    protected string $description = 'Archives a case.';

    public function handle(Request $request): Response
    {
        return Response::text('archived');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
