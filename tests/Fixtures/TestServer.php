<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class TestServer extends Server
{
    protected string $name = 'Test';

    protected array $tools = [WhoAmITool::class, ArchiveCaseTool::class];
}
