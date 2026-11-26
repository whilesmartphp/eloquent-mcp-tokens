<?php

namespace Whilesmart\McpTokens\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Whilesmart\McpTokens\McpTokens;

trait HasMcpTokens
{
    public static function bootHasMcpTokens(): void
    {
        static::deleted(function ($owner) {
            $owner->mcpTokens()->whereNull('revoked_at')->get()->each(fn ($token) => app(McpTokens::class)->revoke($token));
        });
    }

    public function mcpTokens(): MorphMany
    {
        return $this->morphMany(config('mcp-tokens.model'), 'owner');
    }
}
