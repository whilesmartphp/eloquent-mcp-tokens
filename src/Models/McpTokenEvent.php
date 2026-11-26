<?php

namespace Whilesmart\McpTokens\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpTokenEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function getTable(): string
    {
        return config('mcp-tokens.events_table', 'mcp_token_events');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(McpToken::class, 'mcp_token_id');
    }
}
