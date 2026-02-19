# BB-CLI

A PHP CLI tool for interacting with the Bitbucket Cloud REST API.

## Build

```bash
php --define phar.readonly=0 create-phar.php
```

Produces `bb.phar` in the project root. Requires PHP 8.1+ with `curl`, `mbstring`, `json` extensions.

## Architecture

- `bin/bb` — main entry point, routes commands to Action classes
- `src/Base.php` — base class with `makeRequest()` for all Bitbucket API calls
- `src/Actions/` — one class per command group (Auth, Branch, Pr, Pipeline, Browse, etc.)
- `src/utils/helpers.php` — global helper functions (`userConfig()`, `getRepoPath()`, etc.)
- `config/` — default config values
- User config stored at `~/.bitbucket-rest-cli-config.json`

## Auth: Current vs New

### Current: App Passwords
- `bb auth` prompts for **username** + **app password**
- Stored in config as `auth.username` and `auth.appPassword`
- Used in `Base.php:67`: `Authorization: Basic base64(username:appPassword)`

### New: API Tokens (not yet implemented)
Bitbucket API tokens are the long-term replacement for app passwords.

Key differences from app passwords:
- Use **Atlassian account email** (not Bitbucket username) as the identity
- More granular scopes (repo read/write/admin/delete, PR read/write, project, pipeline, etc.)
- Support expiration dates
- Created via: Profile Settings > Security > API Tokens
- Auth header format is the same: `Authorization: Basic base64(email:api_token)`
- For git operations, can use static username `x-bitbucket-api-token-auth` instead of real username
- Token shown only once at creation — cannot be retrieved later
- Docs: https://support.atlassian.com/bitbucket-cloud/docs/api-tokens/
- Usage: https://support.atlassian.com/bitbucket-cloud/docs/using-api-tokens/

### Migration considerations
- Config needs a new field (e.g. `auth.email`) or repurpose `auth.username` to accept email
- Could support both auth methods with a `auth.type` discriminator (`app_password` | `api_token`)
- `Base.php:67` already uses Basic Auth — only the identity value changes (email vs username)
- `Auth.php:saveLoginInfo()` needs updated prompts and config keys
- `Auth.php:show()` should mask the token/password in output
