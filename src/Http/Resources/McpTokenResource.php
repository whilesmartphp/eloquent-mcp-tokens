<?php

namespace Whilesmart\McpTokens\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class McpTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'scopes' => $this->scopes ?? [],
            'status' => $this->status()->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'last_used_at' => $this->accessToken?->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
