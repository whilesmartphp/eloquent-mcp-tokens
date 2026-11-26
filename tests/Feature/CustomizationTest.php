<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Tests\TestCase;
use Whilesmart\McpTokens\Contracts\LifetimePolicy;
use Whilesmart\McpTokens\Contracts\ResponseFormatter;
use Whilesmart\McpTokens\Http\Requests\StoreMcpTokenRequest;
use Whilesmart\McpTokens\Http\Resources\McpTokenResource;
use Whilesmart\McpTokens\McpTokensServiceProvider;

class CustomizationTest extends TestCase
{
    protected function customClasses($app): void
    {
        $app['config']->set('mcp-tokens.resource', LabelledResource::class);
        $app['config']->set('mcp-tokens.response_formatter', BareFormatter::class);
        $app['config']->set('mcp-tokens.store_request', NamedOnlyRequest::class);
        $app['config']->set('mcp-tokens.lifetime_policy', ShortLifetime::class);
    }

    protected function limitedRoutes($app): void
    {
        $app['config']->set('mcp-tokens.actions', ['index', 'store']);
        $app['config']->set('mcp-tokens.write_middleware', [BlockWrites::class]);
    }

    #[DefineEnvironment('customClasses')]
    public function test_hosts_swap_the_request_resource_formatter_and_lifetime_policy(): void
    {
        [, $team, $session] = $this->signIn();

        $this->issue($session, $team, ['name' => 'not allowed'])->assertUnprocessable();
        $created = $this->issue($session, $team, ['name' => 'Codex'])->assertCreated();
        $created->assertJsonPath('payload.label', 'Token Codex')->assertJsonMissingPath('success');
        $this->assertTrue(now()->addDays(3)->diffInMinutes($created->json('payload.expires_at')) < 2);
        $this->issue($session, $team, ['name' => 'Codex', 'days' => 4])->assertUnprocessable();
    }

    #[DefineEnvironment('limitedRoutes')]
    public function test_hosts_choose_the_exposed_actions_and_guard_writes(): void
    {
        [, $team, $session] = $this->signIn();

        $this->issue($session, $team)->assertStatus(423);
        $this->bearer($session)->getJson('api/mcp-tokens')->assertOk();
        $this->bearer($session)->deleteJson('api/mcp-tokens/1')->assertNotFound();
        $this->bearer($session)->getJson('api/mcp-token')->assertNotFound();
    }

    public function test_a_configured_class_that_breaks_its_contract_fails_clearly(): void
    {
        config(['mcp-tokens.resource' => \stdClass::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mcp-tokens.resource');
        (new McpTokensServiceProvider($this->app))->boot();
    }
}

class LabelledResource extends McpTokenResource
{
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'label' => 'Token '.$this->name];
    }
}

class BareFormatter implements ResponseFormatter
{
    public function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json(['payload' => $data], $statusCode);
    }

    public function failure(string $message = 'Operation failed', int $statusCode = 400, array $errors = []): JsonResponse
    {
        return response()->json(['problem' => $message], $statusCode);
    }
}

class NamedOnlyRequest extends StoreMcpTokenRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'name' => ['required', 'in:Codex,Claude Code']];
    }
}

class ShortLifetime implements LifetimePolicy
{
    public function defaultDays(Model $owner): int
    {
        return 3;
    }

    public function maxDays(Model $owner): int
    {
        return 3;
    }

    public function presets(Model $owner): array
    {
        return [1, 3];
    }
}

class BlockWrites
{
    public function handle(Request $request, Closure $next)
    {
        return response()->json(['message' => 'Locked'], 423);
    }
}
