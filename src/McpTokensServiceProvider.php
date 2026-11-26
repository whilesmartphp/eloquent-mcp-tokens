<?php

namespace Whilesmart\McpTokens;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server;
use Whilesmart\McpTokens\Contracts\LifetimePolicy;
use Whilesmart\McpTokens\Contracts\RequestScoper;
use Whilesmart\McpTokens\Contracts\ResponseFormatter;
use Whilesmart\McpTokens\Http\Controllers\McpTokenController;
use Whilesmart\McpTokens\Http\Middleware\EnsureMcpToken;
use Whilesmart\McpTokens\Http\Middleware\RestrictMcpTokens;
use Whilesmart\McpTokens\Http\Requests\StoreMcpTokenRequest;
use Whilesmart\McpTokens\Http\Resources\McpTokenResource;
use Whilesmart\McpTokens\Models\McpToken;

class McpTokensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp-tokens.php', 'mcp-tokens');

        $this->app->singleton(McpTokens::class);
        $this->app->bindIf(ResponseFormatter::class, fn ($app) => $app->make($this->configured('response_formatter', ResponseFormatter::class)));
        $this->app->bindIf(LifetimePolicy::class, fn ($app) => $app->make($this->configured('lifetime_policy', LifetimePolicy::class)));
        $this->app->bindIf(RequestScoper::class, fn ($app) => $app->make($this->configured('request_scoper', RequestScoper::class)));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/mcp-tokens.php' => config_path('mcp-tokens.php')], 'mcp-tokens-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'mcp-tokens-migrations');

        if (config('mcp-tokens.register_routes', true)) {
            $this->configured('model', McpToken::class);
            $this->configured('controller', McpTokenController::class);
            $this->configured('store_request', StoreMcpTokenRequest::class);
            $this->configured('resource', McpTokenResource::class);
            $model = config('mcp-tokens.model');
            Route::bind('mcpToken', fn ($value) => $model::findOrFail($value));

            Route::middleware(config('mcp-tokens.route_middleware', ['api', 'auth:sanctum']))
                ->prefix(config('mcp-tokens.route_prefix', 'api'))
                ->group(__DIR__.'/../routes/api.php');
        }

        if (config('mcp-tokens.restriction.enabled', true)) {
            $this->app->make(Kernel::class)->pushMiddleware(RestrictMcpTokens::class);
        }

        if (config('mcp-tokens.server.enabled', false)) {
            Mcp::web(config('mcp-tokens.server.endpoint', 'mcp'), $this->configured('server.class', Server::class))
                ->middleware([...config('mcp-tokens.server.middleware', []), EnsureMcpToken::class])
                ->name('mcp-tokens.server');
        }
    }

    /** @return class-string */
    private function configured(string $key, string $contract): string
    {
        $class = config('mcp-tokens.'.$key);
        if (! is_string($class) || ! (is_a($class, $contract, true))) {
            throw new InvalidArgumentException("Config mcp-tokens.{$key} must be a class that is or extends {$contract}.");
        }

        return $class;
    }
}
