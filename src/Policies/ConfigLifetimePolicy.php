<?php

namespace Whilesmart\McpTokens\Policies;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\McpTokens\Contracts\LifetimePolicy;

class ConfigLifetimePolicy implements LifetimePolicy
{
    public function defaultDays(Model $owner): int
    {
        return min((int) config('mcp-tokens.lifetime.default_days', 30), $this->maxDays($owner));
    }

    public function maxDays(Model $owner): int
    {
        return (int) config('mcp-tokens.lifetime.max_days', 90);
    }

    public function presets(Model $owner): array
    {
        $max = $this->maxDays($owner);

        return array_values(array_filter(config('mcp-tokens.lifetime.presets', []), fn ($days) => $days <= $max));
    }
}
