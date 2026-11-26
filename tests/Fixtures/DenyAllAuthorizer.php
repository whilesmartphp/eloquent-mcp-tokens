<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

class DenyAllAuthorizer implements OwnerAuthorizer
{
    public function authorize(?Authenticatable $user, string $ownerType, mixed $ownerId): bool
    {
        return false;
    }

    public function scope(Builder $query, ?Authenticatable $user, string $ownerTypeColumn = 'owner_type', string $ownerIdColumn = 'owner_id'): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
