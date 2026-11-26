<?php

namespace Whilesmart\McpTokens\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Whilesmart\McpTokens\Models\McpToken;

class McpTokenFactory extends Factory
{
    protected $model = McpToken::class;

    public function definition(): array
    {
        return [
            'owner_type' => 'team',
            'owner_id' => 1,
            'name' => $this->faker->words(2, true),
            'scopes' => [],
            'expires_at' => now()->addDays(30),
        ];
    }
}
