<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Tests\Fixtures\Project;
use Tests\Fixtures\Team;
use Tests\TestCase;
use Whilesmart\McpTokens\Events\McpTokenCreated;
use Whilesmart\McpTokens\Events\McpTokenRevoked;
use Whilesmart\McpTokens\Models\McpToken;

class McpTokenApiTest extends TestCase
{
    public function test_a_user_creates_a_token_for_an_owner_and_lists_it_without_the_secret(): void
    {
        Event::fake([McpTokenCreated::class]);
        [, $team, $session] = $this->signIn();

        $token = $this->issue($session, $team, ['scopes' => ['read']])->assertCreated()->json('data');

        $this->assertSame(['Claude Code', 'active', ['read'], [7, 30, 90]], [$token['name'], $token['status'], $token['scopes'], $token['presets']]);
        $this->assertTrue(now()->addDays(30)->diffInMinutes($token['expires_at']) < 2);
        $this->assertNotEmpty($token['token']);
        Event::assertDispatched(McpTokenCreated::class, fn ($event) => $event->token->id === $token['id']);

        $listed = $this->bearer($session)->getJson('api/mcp-tokens')->assertOk()->json('data');
        $this->assertSame(1, $listed['meta']['total']);
        $this->assertArrayNotHasKey('token', $listed['data'][0]);
        $this->bearer($token['token'])->getJson('api/mcp-token')->assertOk()->assertJsonPath('data.id', $token['id'])->assertJsonPath('data.owner_id', $team->id);
    }

    public function test_lifetime_scopes_and_owner_are_validated_and_nothing_is_created(): void
    {
        Event::fake([McpTokenCreated::class]);
        [, $team, $session] = $this->signIn();

        $this->issue($session, $team, ['days' => 91])->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->issue($session, $team, ['scopes' => ['billing']])->assertUnprocessable()->assertJsonValidationErrors('scopes.0');
        $this->issue($session, $team, ['owner_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $this->issue($session, $team, ['owner_type' => 'NotAModel'])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $expires = $this->issue($session, $team, ['days' => 7])->assertCreated()->json('data.expires_at');
        $this->assertTrue(now()->addDays(7)->diffInMinutes($expires) < 2);

        Event::assertDispatchedTimes(McpTokenCreated::class, 1);
        $this->assertDatabaseCount('mcp_tokens', 1);
    }

    public function test_a_revoked_or_expired_token_stops_working(): void
    {
        Event::fake([McpTokenRevoked::class]);
        [, $team, $session] = $this->signIn();
        $revoked = $this->issue($session, $team)->json('data');

        $this->bearer($session)->deleteJson('api/mcp-tokens/'.$revoked['id'])->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->bearer($revoked['token'])->getJson('api/mcp-token')->assertUnauthorized();
        Event::assertDispatchedTimes(McpTokenRevoked::class, 1);
        $this->bearer($session)->deleteJson('api/mcp-tokens/'.$revoked['id'])->assertOk();
        Event::assertDispatchedTimes(McpTokenRevoked::class, 1);

        $expiring = $this->issue($session, $team, ['days' => 7])->json('data');
        $this->travel(7)->days();
        $this->travel(1)->minutes();
        $this->bearer($expiring['token'])->getJson('api/mcp-token')->assertUnauthorized();
        $this->bearer($session)->getJson('api/mcp-tokens/'.$expiring['id'].'/activity')->assertOk()->assertJsonPath('data.status', 'expired');
    }

    public function test_a_verified_session_passes_its_two_factor_ability_to_the_token(): void
    {
        [$user, $team] = $this->signIn();
        $verified = $user->createToken('verified', ['2fa-verified'])->plainTextToken;
        $plain = $user->createToken('plain')->plainTextToken;

        $carried = $this->issue($verified, $team)->json('data');
        $bare = $this->issue($plain, $team)->json('data');

        $abilities = fn (array $token) => $user->tokens()->whereKey(McpToken::find($token['id'])->personal_access_token_id)->first()->abilities;
        $this->assertSame(['mcp', '2fa-verified'], $abilities($carried));
        $this->assertSame(['mcp'], $abilities($bare));
    }

    public function test_a_normal_session_has_no_current_mcp_token(): void
    {
        [, , $session] = $this->signIn();

        $this->bearer($session)->getJson('api/mcp-token')->assertNotFound();
    }

    public function test_an_owner_type_morph_alias_is_accepted(): void
    {
        Relation::morphMap(['team' => Team::class]);
        [, $team, $session] = $this->signIn();

        $this->issue($session, $team, ['owner_type' => 'team'])->assertCreated()->assertJsonPath('data.owner_type', 'team');
    }

    public function test_deleting_the_owner_revokes_its_tokens(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data');

        $team->delete();

        $this->bearer($token['token'])->getJson('api/mcp-token')->assertUnauthorized();
        $this->assertNotNull(McpToken::find($token['id'])->revoked_at);
    }

    #[DefineEnvironment('uuidOwners')]
    public function test_owners_with_uuid_keys_are_supported(): void
    {
        [, , $session] = $this->signIn();
        $this->assertSame('varchar', Schema::getColumnType('mcp_tokens', 'owner_id'));
        $project = Project::create(['name' => 'Site']);

        $token = $this->bearer($session)->postJson('api/mcp-tokens', ['owner_type' => Project::class, 'owner_id' => $project->id, 'name' => 'Cursor'])
            ->assertCreated()->assertJsonPath('data.owner_id', $project->id)->json('data.token');
        $this->bearer($token)->getJson('api/v1/cases')->assertOk();
    }

    protected function uuidOwners($app): void
    {
        $app['config']->set('mcp-tokens.owner_key_type', 'uuid');
    }
}
