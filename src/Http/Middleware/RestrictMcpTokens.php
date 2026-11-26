<?php

namespace Whilesmart\McpTokens\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Whilesmart\McpTokens\Contracts\RequestScoper;
use Whilesmart\McpTokens\Contracts\ResponseFormatter;
use Whilesmart\McpTokens\McpTokens;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

/**
 * Refuses an MCP token everywhere except the package's own routes, the MCP endpoint and the host's
 * allowlist. It runs as global middleware so routes registered without a middleware group are covered,
 * which means it matches the route itself rather than reading the request's route.
 */
class RestrictMcpTokens
{
    public const OWN_ROUTES = ['mcp-tokens.current', 'mcp-tokens.server'];

    public function __construct(private McpTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() || ! ($token = $this->tokens->current(Auth::guard('sanctum')->user()))) {
            return $next($request);
        }

        if (! app(OwnerAuthorizer::class)->authorize($request->user('sanctum'), $token->owner_type, $token->owner_id)) {
            return $this->refuse('The token\'s user can no longer access its owner.', 'mcp_token_owner_access_lost');
        }

        try {
            $route = app('router')->getRoutes()->match($request);
        } catch (HttpException) {
            return $this->refuse();
        }

        $allow = config('mcp-tokens.restriction.allow', []);
        $key = $request->method().' '.$route->uri();
        if (! in_array($route->getName(), self::OWN_ROUTES, true) && ! array_key_exists($key, $allow)) {
            return $this->refuse();
        }
        if ($refusal = app(RequestScoper::class)->scope($request, $token)) {
            return $refusal;
        }

        $response = $next($request);

        $entry = $allow[$key] ?? null;
        $action = is_array($entry) ? ($entry['action'] ?? null) : $entry;
        if ($action && $response->isSuccessful() && $response instanceof JsonResponse) {
            $body = $response->getData(true);
            $subject = is_array($entry) && isset($entry['subject']) ? data_get($body, $entry['subject']) : ($body['data']['id'] ?? $body['id'] ?? null);
            $this->tokens->record($token, $action, is_scalar($subject) ? $subject : null);
        }

        return $response;
    }

    private function refuse(string $message = 'An MCP token cannot make this request.', string $code = 'mcp_token_forbidden'): Response
    {
        return app(ResponseFormatter::class)->failure($message, 403, ['code' => $code]);
    }
}
