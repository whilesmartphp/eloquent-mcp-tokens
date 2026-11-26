<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Fixtures\Team;
use Tests\Fixtures\TestServer;
use Tests\Fixtures\User;
use Whilesmart\McpTokens\McpTokensServiceProvider;
use Whilesmart\OwnerAccess\OwnerAccessServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/sanctum/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            OwnerAccessServiceProvider::class,
            SanctumServiceProvider::class,
            McpServiceProvider::class,
            McpTokensServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mcp-tokens.route_middleware', ['api', 'auth:sanctum']);
        $app['config']->set('mcp-tokens.scopes', ['read', 'write']);
        $app['config']->set('mcp-tokens.restriction.allow', ['GET api/v1/cases' => null, 'POST api/v1/cases' => 'case_created']);
        $app['config']->set('mcp-tokens.server.enabled', true);
        $app['config']->set('mcp-tokens.server.class', TestServer::class);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('auth:sanctum')->group(function ($router) {
            $router->get('api/v1/cases', fn () => response()->json(['data' => [], 'tenant' => request()->header('X-Tenant')]));
            $router->post('api/v1/cases', fn () => response()->json(['id' => 42], 201));
            $router->get('api/v1/billing', fn () => response()->json(['data' => 'billing']));
        });
    }

    /** @return array{0: User, 1: Team, 2: string} a user, a team they belong to and a normal session token */
    protected function signIn(array $abilities = ['*']): array
    {
        $user = User::create(['name' => 'Ada', 'email' => uniqid().'@example.test', 'password' => 'secret']);
        $team = Team::create(['name' => 'Ada team']);

        return [$user, $team, $user->createToken('session', $abilities)->plainTextToken];
    }

    protected function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
    }

    protected function issue(string $session, Team $team, array $extra = []): TestResponse
    {
        return $this->bearer($session)->postJson('api/mcp-tokens', ['owner_type' => Team::class, 'owner_id' => $team->id, 'name' => 'Claude Code', ...$extra]);
    }
}
