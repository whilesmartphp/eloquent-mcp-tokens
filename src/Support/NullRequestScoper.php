<?php

namespace Whilesmart\McpTokens\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\McpTokens\Contracts\RequestScoper;
use Whilesmart\McpTokens\Models\McpToken;

class NullRequestScoper implements RequestScoper
{
    public function scope(Request $request, McpToken $token): ?Response
    {
        return null;
    }
}
