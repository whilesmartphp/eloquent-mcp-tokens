<?php

namespace Whilesmart\McpTokens\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\McpTokens\Models\McpToken;

/**
 * Pins an allowed request to the token's owner before it reaches the host's routes, for example by
 * setting the tenant headers the host reads. Returning a response refuses the request with it.
 */
interface RequestScoper
{
    public function scope(Request $request, McpToken $token): ?Response;
}
