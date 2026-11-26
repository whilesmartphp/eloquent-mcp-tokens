<?php

namespace Whilesmart\McpTokens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Whilesmart\McpTokens\Contracts\LifetimePolicy;
use Whilesmart\McpTokens\Contracts\ResponseFormatter;
use Whilesmart\McpTokens\Http\Requests\StoreMcpTokenRequest;
use Whilesmart\McpTokens\McpTokens;
use Whilesmart\McpTokens\Models\McpToken;
use Whilesmart\OwnerAccess\Concerns\AuthorizesOwnerController;

class McpTokenController extends Controller
{
    use AuthorizesOwnerController;

    public function __construct(protected McpTokens $tokens, protected ResponseFormatter $responses) {}

    public function index(Request $request): JsonResponse
    {
        $model = config('mcp-tokens.model');
        $query = $this->scopeAccessibleOwners($model::query(), $request->user())->with('accessToken');

        if ($request->filled('owner_type') && $request->filled('owner_id')) {
            $query->where('owner_type', $request->input('owner_type'))->where('owner_id', $request->input('owner_id'));
        }

        $tokens = $query->latest('id')->paginate((int) $request->input('per_page', 25));

        return $this->responses->success($this->resource()::collection($tokens)->response()->getData(true));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var StoreMcpTokenRequest $form */
        $form = app(config('mcp-tokens.store_request'));
        $owner = $form->owner();
        [$token, $plain] = $this->tokens->issue($owner, $request->user(), $form->input('name'), $form->days(), $form->input('scopes', []));

        return $this->responses->success([
            ...$this->resource()::make($token->load('accessToken'))->resolve($request),
            'token' => $plain,
            'presets' => app(LifetimePolicy::class)->presets($owner),
        ], 'MCP token created', 201);
    }

    public function destroy(Request $request, McpToken $mcpToken): JsonResponse
    {
        $this->authorizeAccessTo($mcpToken, $request->user());

        return $this->responses->success($this->resource()::make($this->tokens->revoke($mcpToken))->resolve($request), 'MCP token revoked');
    }

    public function activity(Request $request, McpToken $mcpToken): JsonResponse
    {
        $this->authorizeAccessTo($mcpToken, $request->user());
        $events = $mcpToken->events()->latest('id')->limit(100)->get();

        return $this->responses->success([
            ...$this->resource()::make($mcpToken->load('accessToken'))->resolve($request),
            'counts' => $events->countBy('action')->all(),
            'events' => config('mcp-tokens.event_resource')::collection($events)->resolve($request),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $token = $this->tokens->current($request->user());
        abort_unless($token !== null, 404);

        return $this->responses->success($this->resource()::make($token->load('accessToken'))->resolve($request));
    }

    /** @return class-string<JsonResource> */
    protected function resource(): string
    {
        return config('mcp-tokens.resource');
    }
}
