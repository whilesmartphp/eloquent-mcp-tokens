<?php

namespace Whilesmart\McpTokens\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\McpTokens\Models\McpToken;

class McpTokenRevoked
{
    use Dispatchable;

    public function __construct(public readonly McpToken $token) {}
}
