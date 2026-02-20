# BB-CLI

A PHP CLI tool for interacting with the Bitbucket Cloud REST API.

## Build

```bash
php --define phar.readonly=0 create-phar.php
# or
make build
```

Produces `bb.phar` in the project root. Requires PHP 8.1+ with `curl`, `mbstring`, `json`, `openssl` extensions.

## Development Workflow

**Test first. Write tests before or alongside code. Always run before committing.**

The loop: write test → write/revise code → run tests → repeat.

```bash
make test          # run phpunit
make build         # rebuild bb.phar
```

A pre-commit hook runs `make test` automatically — commits will fail if tests don't pass.

**Keep commits clean.** If you make several small iterative commits while working, squash them before moving on:

```bash
git reset --soft HEAD~n   # squash last n commits back to staged
git commit --amend        # fold current changes into the previous commit
```

## Architecture

- `bin/bb` — main entry point, routes commands to Action classes
- `src/Base.php` — base class with `makeRequest()` for all Bitbucket API calls
- `src/Actions/` — one class per command group (Auth, Branch, Pr, Pipeline, Browse, etc.)
- `src/utils/helpers.php` — global helper functions (`userConfig()`, `getRepoPath()`, `encryptSecret()`, etc.)
- `config/app.php` — app config; supports `BB_CLI_CONFIG` env override for tests
- `tests/` — PHPUnit test suite (uses `tests/phpunit.phar`, gitignored — download separately)
- `skills/bb-cli/SKILL.md` — Claude skill for LLM consumers of this tool
- User config stored at `~/.local/share/bb-cli/config.json` (migrated from `~/.bitbucket-rest-cli-config.json`)

## Auth

Two modes, selected by `auth.type` in config:

### API Tokens (recommended)
- `bb auth token` — prompts for Atlassian account **email** + **API token**
- Token created at: Profile Settings > Security > API Tokens
- Auth header: `Authorization: Basic base64(email:token)`
- Git credential helper uses static username `x-bitbucket-api-token-auth`
- Docs: https://support.atlassian.com/bitbucket-cloud/docs/api-tokens/

### App Passwords (legacy, deprecated July 2025)
- `bb auth save` — prompts for **username** + **app password**
- Auth header: `Authorization: Basic base64(username:appPassword)`

## Credential Storage

Config is stored at `~/.local/share/bb-cli/config.json` with `chmod 0600`.

Secret fields (`apiToken`, `appPassword`) are encrypted at rest using AES-256-CBC with a key derived from the user's UID + machine UUID. Transparent on read/write — no password prompt. Switching machines requires re-running `bb auth token`.

Corrupt or wrong-machine tokens produce:
> "Your token is invalid or corrupt. Please request a new one at: https://id.atlassian.com/manage-profile/security/api-tokens"

## Testing

```bash
make test
php tests/phpunit.phar --filter SomeTest
```

Tests use `BB_CLI_CONFIG` env var to redirect config I/O to temp files — no real config is touched. Do not stub `config()` or `userConfig()` — use real implementations with temp files.
