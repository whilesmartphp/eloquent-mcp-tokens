<?php

namespace Tests\Feature;

use Tests\Fixtures\DenyAllAuthorizer;
use Tests\TestCase;
use Whilesmart\McpTokens\McpTokens;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

class McpTokenAuthorizationTest extends TestCase
{
    public function test_a_user_outside_the_owner_cannot_create_list_revoke_or_read_activity(): void
    {
        [$user, $team, $session] = $this->signIn();
        [$token] = app(McpTokens::class)->issue($team, $user, 'Existing', 30);
        $this->app->instance(OwnerAuthorizer::class, new DenyAllAuthorizer);

        $this->issue($session, $team)->assertForbidden();
        $this->bearer($session)->getJson('api/mcp-tokens')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->bearer($session)->deleteJson('api/mcp-tokens/'.$token->id)->assertForbidden();
        $this->bearer($session)->getJson('api/mcp-tokens/'.$token->id.'/activity')->assertForbidden();
        $this->assertNull($token->fresh()->revoked_at);
        $this->assertDatabaseCount('mcp_tokens', 1);
    }
}
