<?php

namespace Whilesmart\McpTokens\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Decides how long a token for an owner may live, so hosts can apply per-owner or per-organization limits. */
interface LifetimePolicy
{
    public function defaultDays(Model $owner): int;

    public function maxDays(Model $owner): int;

    /** @return list<int> */
    public function presets(Model $owner): array;
}
