# eloquent-mcp-tokens

Owner-scoped MCP tokens for Laravel applications that expose an MCP server with
[`laravel/mcp`](https://github.com/laravel/mcp).

A user creates a token for something they can access (a workspace, a team). A coding agent uses it
as a bearer token. The token:

- belongs to one owner and expires (the lifetime policy decides how long);
- carries only the scopes it was given;
- can reach the package's own routes, the MCP endpoint and the routes the host allows, nothing else;
- stops working when it is revoked, expires, its owner is deleted, or its user loses access to the owner;
- records what it did, so the user can watch an agent's progress and revoke it.

laravel/mcp supports two ways to authenticate a web server: a bearer token (Sanctum) or OAuth 2.1
(Passport, with `Mcp::oauthRoutes()`). This package is the bearer token way. Use OAuth instead when
users should connect by signing in from the client without copying a token; both can run side by side.

## Install

```bash
composer require whilesmart/eloquent-mcp-tokens
php artisan vendor:publish --tag=mcp-tokens-config
php artisan migrate
```

Owners with UUID or ULID keys: set `owner_key_type` to `uuid` or `ulid` before migrating.

Requires Laravel 12.41.1 or later, `laravel/sanctum` and `whilesmart/eloquent-owner-access`. Bind an
`OwnerAuthorizer` so only members of an owner can manage its tokens.

## Endpoints

All under `route_prefix` (default `api`) and `route_middleware` (default `api`, `auth:sanctum`).

| Method | Path | Does |
| --- | --- | --- |
| GET | `/mcp-tokens` | Tokens of owners the user can access, paginated. Filter with `owner_type` and `owner_id`. |
| POST | `/mcp-tokens` | Create a token: `owner_type`, `owner_id`, `name`, optional `days` and `scopes`. The plain token is returned once. |
| DELETE | `/mcp-tokens/{id}` | Revoke. The bearer token stops working at once. |
| GET | `/mcp-tokens/{id}/activity` | Status, last use, counts per action and the latest events. |
| GET | `/mcp-token` | The calling MCP token, for an agent to check its own scope. |

## Restricting tokens

`RestrictMcpTokens` runs as global middleware, so it also covers routes registered without a
middleware group. An MCP token is refused with 403 (`errors.code` `mcp_token_forbidden`) unless the
route is the package's own `/mcp-token`, the MCP endpoint, or listed in `restriction.allow`:

```php
'restriction' => [
    'allow' => [
        'GET api/v1/cases' => null,
        'POST api/v1/cases' => 'case_created', // recorded as activity on success
    ],
],
```

Keys are the HTTP method and the route URI pattern. Normal sessions are never restricted.

Bind a `RequestScoper` to pin an allowed request to the token's owner, for example by setting the
tenant header your routes read, or to refuse it by returning a response.

## Serving MCP

```php
'server' => [
    'enabled' => true,
    'endpoint' => 'mcp',
    'class' => App\Mcp\AppServer::class, // extends Laravel\Mcp\Server
    'middleware' => ['auth:sanctum', 'throttle:60,1'],
],
```

Only MCP tokens may call the endpoint; set `server.tokens_only` to `false` to also accept other
Sanctum tokens. A request without a valid token gets 401 with `WWW-Authenticate: Bearer`.

Tools use `InteractsWithMcpToken` to reach the calling token. A tool marked `#[RequiresScope('write')]`
is left out of `tools/list` and refused on `tools/call` for tokens without that scope:

```php
use Whilesmart\McpTokens\Concerns\InteractsWithMcpToken;

#[RequiresScope('write')]
class CreateProfileTool extends Tool
{
    use InteractsWithMcpToken;

    public function handle(Request $request): Response
    {
        $token = $this->mcpToken($request); // $token->owner is the workspace
        // ...
        $this->recordActivity($request, 'profile_created', $profile->id);
    }
}
```

## Connecting a client

Every major coding agent accepts a remote MCP server with a bearer header. With the token in
`MCP_TOKEN` and the server at `https://app.example.com/mcp`:

Claude Code:

```bash
claude mcp add --transport http app https://app.example.com/mcp --header "Authorization: Bearer $MCP_TOKEN"
```

Codex (`~/.codex/config.toml`):

```toml
[mcp_servers.app]
url = "https://app.example.com/mcp"
bearer_token_env_var = "MCP_TOKEN"
```

Cursor (`.cursor/mcp.json`):

```json
{ "mcpServers": { "app": { "url": "https://app.example.com/mcp", "headers": { "Authorization": "Bearer ${env:MCP_TOKEN}" } } } }
```

Claude Code and Codex start an OAuth sign-in when the server answers 401 or 403 and you run their login
command. Without `Mcp::oauthRoutes()` that sign-in has nothing to find, so an expired token means
creating a new one.

## Customizing

| Need | Config or binding |
| --- | --- |
| Extra columns, relations | `model` (must extend `McpToken`) |
| Validation, owner resolution | `store_request` (extend `StoreMcpTokenRequest`) |
| Token JSON | `resource`, `event_resource` |
| Response envelope | `response_formatter` (`ResponseFormatter`) |
| Lifetime per owner | `lifetime_policy` (`LifetimePolicy`) |
| Pin requests to the owner | `request_scoper` (`RequestScoper`) |
| Which routes exist | `actions` |
| Accept non-MCP tokens on the endpoint | `server.tokens_only` |
| Guard create and revoke | `write_middleware` |
| Everything else | `controller` |

`McpTokenCreated` and `McpTokenRevoked` are dispatched after the change is saved.

## Tests

```bash
make bootstrap && make test
```
