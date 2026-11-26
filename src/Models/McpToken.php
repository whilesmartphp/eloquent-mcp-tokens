<?php

namespace Whilesmart\McpTokens\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken;
use Whilesmart\McpTokens\Database\Factories\McpTokenFactory;
use Whilesmart\McpTokens\Enums\McpTokenStatus;

class McpToken extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('mcp-tokens.tokens_table', 'mcp_tokens');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(McpTokenEvent::class, 'mcp_token_id');
    }

    public function status(): McpTokenStatus
    {
        if ($this->revoked_at !== null) {
            return McpTokenStatus::Revoked;
        }

        return $this->expires_at->isPast() ? McpTokenStatus::Expired : McpTokenStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status() === McpTokenStatus::Active;
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    protected static function newFactory(): McpTokenFactory
    {
        return McpTokenFactory::new();
    }
}
