<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\DenyAllAuthorizer;
use Tests\TestCase;
use Whilesmart\McpTokens\Contracts\RequestScoper;
use Whilesmart\McpTokens\Http\Middleware\RestrictMcpTokens;
use Whilesmart\McpTokens\Models\McpToken;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

class RestrictMcpTokensTest extends TestCase
{
    public function test_an_mcp_token_reaches_only_the_allowlist_and_its_writes_are_recorded(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data');

        $this->bearer($token['token'])->getJson('api/v1/cases')->assertOk();
        $this->bearer($token['token'])->postJson('api/v1/cases')->assertCreated();
        foreach ([['GET', 'api/v1/billing'], ['GET', 'api/mcp-tokens'], ['POST', 'api/mcp-tokens'], ['DELETE', 'api/mcp-tokens/'.$token['id']], ['GET', 'api/v1/unknown']] as [$method, $uri]) {
            $this->assertSame(403, $this->bearer($token['token'])->json($method, $uri)->status(), $method.' '.$uri);
        }
        $this->bearer($token['token'])->getJson('api/v1/billing')->assertJsonPath('errors.code', 'mcp_token_forbidden');

        $activity = $this->bearer($session)->getJson('api/mcp-tokens/'.$token['id'].'/activity')->assertOk()->json('data');
        $this->assertSame(['case_created' => 1], $activity['counts']);
        $this->assertSame('42', $activity['events'][0]['subject_id']);
        $this->assertNotNull($activity['last_used_at']);
    }

    public function test_an_allowlist_entry_can_name_where_the_subject_id_is(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data');

        $this->bearer($token['token'])->postJson('api/v1/keys')->assertCreated();

        $this->bearer($session)->getJson('api/mcp-tokens/'.$token['id'].'/activity')
            ->assertJsonPath('data.counts', ['key_created' => 1])->assertJsonPath('data.events.0.subject_id', 'cl_7');
    }

    public function test_a_token_stops_working_once_its_user_loses_access_to_the_owner(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data.token');
        $this->app->instance(OwnerAuthorizer::class, new DenyAllAuthorizer);

        $this->bearer($token)->getJson('api/v1/cases')->assertForbidden()->assertJsonPath('errors.code', 'mcp_token_owner_access_lost');
        $this->bearer($token)->getJson('api/mcp-token')->assertForbidden();
    }

    public function test_a_first_request_without_a_user_resolver_is_checked_against_the_guard_user(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data.token');
        $this->app['auth']->forgetGuards();
        $request = Request::create('api/v1/cases', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => null);
        $this->app->instance(OwnerAuthorizer::class, new class extends DenyAllAuthorizer
        {
            public function authorize(?Authenticatable $user, string $ownerType, mixed $ownerId): bool
            {
                return $user !== null;
            }
        });

        $response = $this->app->make(RestrictMcpTokens::class)->handle($request, fn () => response()->json(['passed' => true]));

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function test_a_normal_session_is_not_restricted(): void
    {
        [, , $session] = $this->signIn();

        $this->bearer($session)->getJson('api/v1/billing')->assertOk();
    }

    public function test_the_request_scoper_pins_or_refuses_an_allowed_request(): void
    {
        $this->app->instance(RequestScoper::class, new class implements RequestScoper
        {
            public function scope(Request $request, McpToken $token): ?Response
            {
                if ($request->hasHeader('X-Tenant') && $request->header('X-Tenant') !== (string) $token->owner_id) {
                    return response()->json(['message' => 'Other tenant'], 403);
                }
                $request->headers->set('X-Tenant', (string) $token->owner_id);

                return null;
            }
        });
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team)->json('data');

        $this->bearer($token['token'])->getJson('api/v1/cases')->assertOk()->assertJsonPath('tenant', (string) $team->id);
        $this->bearer($token['token'])->withHeader('X-Tenant', '999')->getJson('api/v1/cases')->assertForbidden()->assertJsonPath('message', 'Other tenant');
    }
}
