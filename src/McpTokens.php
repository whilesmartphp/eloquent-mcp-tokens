<?php

namespace Whilesmart\McpTokens;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Whilesmart\McpTokens\Events\McpTokenCreated;
use Whilesmart\McpTokens\Events\McpTokenRevoked;
use Whilesmart\McpTokens\Models\McpToken;
use Whilesmart\McpTokens\Models\McpTokenEvent;

/** Issues, resolves and revokes MCP tokens, and records what a token did. */
class McpTokens
{
    /**
     * @param  list<string>  $scopes
     * @return array{0: McpToken, 1: string} the token and its plain text value, which is never stored
     */
    public function issue(Model $owner, Authenticatable $creator, string $name, int $days, array $scopes = []): array
    {
        $abilities = [$this->ability(), ...array_map(fn ($scope) => $this->ability().':'.$scope, $scopes)];
        $session = method_exists($creator, 'currentAccessToken') ? $creator->currentAccessToken() : null;
        if ($session instanceof PersonalAccessToken) {
            foreach (config('mcp-tokens.carry_abilities', []) as $ability) {
                if (in_array($ability, $session->abilities ?? [], true)) {
                    $abilities[] = $ability;
                }
            }
        }

        [$token, $plain] = DB::transaction(function () use ($owner, $creator, $name, $days, $scopes, $abilities) {
            $expires = now()->addDays($days);
            $access = $creator->createToken(config('mcp-tokens.ability', 'mcp').': '.$name, $abilities, $expires);
            $model = config('mcp-tokens.model');
            $token = $model::create([
                'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
                'creator_type' => $creator->getMorphClass(), 'creator_id' => $creator->getAuthIdentifier(),
                'personal_access_token_id' => $access->accessToken->getKey(),
                'name' => $name, 'scopes' => array_values($scopes), 'expires_at' => $expires,
            ]);

            return [$token, $access->plainTextToken];
        });

        McpTokenCreated::dispatch($token);

        return [$token, $plain];
    }

    public function revoke(McpToken $token): McpToken
    {
        if ($token->revoked_at !== null) {
            return $token;
        }

        DB::transaction(function () use ($token) {
            $token->accessToken()->delete();
            $token->update(['revoked_at' => now()]);
        });

        McpTokenRevoked::dispatch($token);

        return $token;
    }

    /** The MCP token behind the user's current Sanctum token, when it is an active one. */
    public function current(?Authenticatable $user): ?McpToken
    {
        $access = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! $access instanceof PersonalAccessToken || ! $this->isMcpAccessToken($access)) {
            return null;
        }

        $model = config('mcp-tokens.model');

        return $model::where('personal_access_token_id', $access->getKey())->whereNull('revoked_at')->first();
    }

    /** Sanctum's can() is true for "*" tokens, so the MCP ability is checked literally. */
    public function isMcpAccessToken(PersonalAccessToken $access): bool
    {
        return in_array($this->ability(), $access->abilities ?? [], true);
    }

    public function record(McpToken $token, string $action, string|int|null $subjectId = null, array $metadata = []): McpTokenEvent
    {
        return $token->events()->create([
            'action' => $action,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function ability(): string
    {
        return config('mcp-tokens.ability', 'mcp');
    }
}
