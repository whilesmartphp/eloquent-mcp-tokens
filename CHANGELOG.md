# Changelog

## [1.0.0] - 2026-11-26

- Owner-scoped MCP tokens on Sanctum: create, list, revoke, read activity and read the calling token.
- Token lifetime policy with a default, a maximum and presets, replaceable per owner.
- Scopes granted per token, checked by host tools.
- Global restriction that keeps MCP tokens to the package routes, the MCP endpoint and a host allowlist, with activity recorded for allowlisted writes; an entry can name where the subject id is in the response.
- Request scoper contract to pin allowed requests to the token's owner.
- A token stops working when its owner is deleted or its user loses access to the owner.
- `#[RequiresScope]` hides and refuses a tool for tokens without the scope.
- UUID and ULID owner keys.
- Optional mounting of a host `laravel/mcp` server behind Sanctum and a rate limit, for MCP tokens only by default.
- Configurable model, controller, request, resources, response formatter, exposed actions and write middleware.
