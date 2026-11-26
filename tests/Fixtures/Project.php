<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\McpTokens\Traits\HasMcpTokens;

class Project extends Model
{
    use HasMcpTokens, HasUuids;

    protected $guarded = [];
}
