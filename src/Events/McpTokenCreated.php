<?php

namespace Whilesmart\McpTokens\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\McpTokens\Models\McpToken;

class McpTokenCreated
{
    use Dispatchable;

    public function __construct(public readonly McpToken $token) {}
}
