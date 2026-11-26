<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\McpTokens\Traits\HasMcpTokens;

class Team extends Model
{
    use HasMcpTokens;

    protected $guarded = [];
}
