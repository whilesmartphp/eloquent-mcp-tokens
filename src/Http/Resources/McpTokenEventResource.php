<?php

namespace Whilesmart\McpTokens\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class McpTokenEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'action' => $this->action,
            'subject_id' => $this->subject_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
