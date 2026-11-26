<?php

namespace Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    private function rpc(string $token, string $method, array $params = [], array $headers = [])
    {
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [
            ...$params,
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => []],
        ]];

        return $this->bearer($token)->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, ...$headers])
            ->postJson('mcp', $message);
    }

    private function callTool(string $token, string $name)
    {
        return $this->rpc($token, 'tools/call', ['name' => $name, 'arguments' => []], ['Mcp-Name' => $name]);
    }

    private function toolNames(string $token): array
    {
        return array_column($this->rpc($token, 'tools/list')->assertOk()->json('result.tools'), 'name');
    }

    protected function sessionsMayCall($app): void
    {
        $app['config']->set('mcp-tokens.server.tokens_only', false);
    }

    public function test_an_mcp_token_calls_a_host_tool_that_sees_its_owner_scopes_and_records_activity(): void
    {
        [, $team, $session] = $this->signIn();
        $token = $this->issue($session, $team, ['scopes' => ['write']])->json('data');

        $this->callTool($token['token'], 'whoami')->assertOk()->assertJsonPath('result.content.0.text', "owner {$team->id}, write yes");

        $this->bearer($session)->getJson('api/mcp-tokens/'.$token['id'].'/activity')->assertJsonPath('data.counts', ['whoami' => 1]);
    }

    public function test_a_tool_that_requires_a_scope_is_hidden_from_and_refused_to_tokens_without_it(): void
    {
        [, $team, $session] = $this->signIn();
        $reader = $this->issue($session, $team, ['scopes' => ['read']])->json('data.token');
        $writer = $this->issue($session, $team, ['scopes' => ['write']])->json('data.token');

        $this->assertSame(['whoami'], $this->toolNames($reader));
        $this->callTool($reader, 'archive-case')->assertJsonPath('error.message', 'Tool [archive-case] not found.');
        $this->assertSame(['whoami', 'archive-case'], $this->toolNames($writer));
        $this->callTool($writer, 'archive-case')->assertJsonPath('result.content.0.text', 'archived');
    }

    public function test_a_missing_or_dead_token_gets_401_with_a_bearer_challenge(): void
    {
        $this->postJson('mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
    }

    public function test_a_normal_session_is_refused_by_default(): void
    {
        [, , $session] = $this->signIn();

        $this->callTool($session, 'whoami')->assertForbidden()->assertJsonPath('errors.code', 'mcp_token_required');
    }

    #[DefineEnvironment('sessionsMayCall')]
    public function test_hosts_can_let_normal_sessions_call_tools_without_scoped_ones(): void
    {
        [, , $session] = $this->signIn();

        $this->callTool($session, 'whoami')->assertOk()->assertJsonPath('result.content.0.text', 'no mcp token');
        $this->assertSame(['whoami', 'archive-case'], $this->toolNames($session));
    }
}
