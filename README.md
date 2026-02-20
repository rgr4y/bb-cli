# bb

A fast, opinionated Bitbucket CLI. Manage pull requests, branches, pipelines,
and environments from your terminal — no browser required.

```
  ██████╗ ██████╗      ██████╗██╗     ██╗
  ██╔══██╗██╔══██╗    ██╔════╝██║     ██║
  ██████╔╝██████╔╝    ██║     ██║     ██║
  ██╔══██╗██╔══██╗    ██║     ██║     ██║
  ██████╔╝██████╔╝    ╚██████╗███████╗██║
  ╚═════╝ ╚═════╝      ╚═════╝╚══════╝╚═╝
```

![bb demo](demo.gif)

## Requirements

- PHP 8.1+ with `curl`, `mbstring`, `json` extensions
- A Bitbucket Cloud account with an [API token](https://support.atlassian.com/bitbucket-cloud/docs/api-tokens/)

## Installation

**From a release binary** (no PHP needed at runtime — it's a self-contained PHAR):

```sh
curl -L https://github.com/rgr4y/bb-cli/releases/latest/download/bb -o bb
chmod +x bb
./bb install        # installs to ~/.local/bin/bb
```

**From source:**

```sh
git clone https://github.com/rgr4y/bb-cli
cd bb-cli
make install        # builds + copies to ~/.local/bin/bb
```

## Setup

```sh
bb auth             # save your Bitbucket API token
bb git setup        # configure git to auto-authenticate with your token
```

`bb auth` will verify your credentials against the Bitbucket API before saving.
`bb git setup` installs a git credential helper scoped to `bitbucket.org` — no
more password prompts on push/pull.

> **Note:** App passwords still work via `bb auth save`, but Bitbucket is
> deprecating them. API tokens are the way forward.

## Usage

```
bb <command> [subcommand] [args] [--project owner/repo]
```

**Global flags:**

| Flag | Description |
|------|-------------|
| `--project <owner/repo>` | Target a specific repo instead of the local git context |
| `--json` | Output as JSON (useful for scripting) |
| `--help`, `-h` | Show help for any command |
| `--version`, `-v` | Show version |

---

## Commands

### `auth` — Manage credentials

| Subcommand | Description |
|------------|-------------|
| `bb auth` / `bb auth token` | Save an Atlassian API token (recommended) |
| `bb auth repo-token` | Save a repository access token (single repo scope) |
| `bb auth save` | Save an app password (legacy) |
| `bb auth show` | Show current auth configuration |
| `bb auth composer-auth` | Generate Composer `auth.json` for private Bitbucket packages |

---

### `pr` — Pull requests

| Subcommand | Aliases | Description |
|------------|---------|-------------|
| `bb pr list [dest]` | `l` | List open PRs, optionally filtered by destination branch |
| `bb pr create <to> [from]` | | Create a PR (defaults to current branch) |
| `bb pr view <id>` | `show` | Show PR title, state, reviewers, and link |
| `bb pr diff <id>` | `d` | Show PR diff |
| `bb pr files <id>` | | List files changed in PR |
| `bb pr commits <id>` | `checks`, `c` | List commits in PR |
| `bb pr comments <id>` | | List PR comments |
| `bb pr approve <id>` | `a` | Approve a PR (`0` = approve all open PRs) |
| `bb pr no-approve <id>` | `na` | Remove your approval |
| `bb pr request-changes <id>` | `rc` | Request changes on a PR |
| `bb pr no-request-changes <id>` | `nrc` | Remove request-changes |
| `bb pr merge <id>` | `m` | Merge a PR |
| `bb pr decline <id>` | `close` | Decline / close a PR |

---

### `branch` — Branches

| Subcommand | Aliases | Description |
|------------|---------|-------------|
| `bb branch list` | `l` | List all branches |
| `bb branch user [name]` | `u` | Filter branches by commit author |
| `bb branch name <filter>` | `n` | Filter branches by name |

---

### `pipeline` — Pipelines

| Subcommand | Aliases | Description |
|------------|---------|-------------|
| `bb pipeline latest` | | Show latest pipeline status |
| `bb pipeline get <id>` | | Show details for a specific pipeline |
| `bb pipeline wait [id]` | | Block until a pipeline finishes (defaults to latest) |
| `bb pipeline run <branch>` | | Trigger the default pipeline on a branch |
| `bb pipeline custom <branch> <name>` | `c` | Trigger a custom pipeline on a branch |

---

### `env` — Deployment environments & variables

| Subcommand | Aliases | Description |
|------------|---------|-------------|
| `bb env list` | `l` | List deployment environments |
| `bb env variables <env-uuid>` | `v` | List variables for an environment |
| `bb env create-variable <env-uuid> <key> <value>` | `c` | Create a variable |
| `bb env update-variable <env-uuid> <var-uuid> <key> <value>` | `u` | Update a variable |

---

### `browse` — Open in browser

| Subcommand | Aliases | Description |
|------------|---------|-------------|
| `bb browse` | `b` | Open the repo in your default browser |
| `bb browse show` | `url` | Print the repo URL |

---

### `git` — Git credential helper

| Subcommand | Description |
|------------|-------------|
| `bb git setup` | Install a git credential helper for `bitbucket.org` — eliminates password prompts |
| `bb git remove` | Uninstall the credential helper |

---

### `install` — Install to PATH

```sh
bb install          # copies bb to ~/.local/bin (or first writable dir on PATH)
```

---

### `upgrade` — Self-update

```sh
bb upgrade          # checks GitHub for the latest release and installs it
```

---

## Quick examples

```sh
# Pull requests
bb pr list                          # open PRs in current repo
bb pr create main                   # open PR → main from current branch
bb pr view 42                       # details for PR #42
bb pr files 42                      # files changed in PR #42
bb pr approve 42                    # approve PR #42
bb pr approve 0                     # approve all open PRs

# Branches
bb branch list                      # all branches
bb branch user rob                  # branches by author
bb branch name feat                 # branches matching "feat"

# Pipelines
bb pipeline latest                  # latest pipeline status
bb pipeline wait                    # block until pipeline finishes
bb pipeline run main                # trigger pipeline on main

# Environments
bb env list
bb env variables <env-uuid>

# Work on a remote repo without cloning
bb pr list --project org/repo
```

## Building

```sh
make build          # produces ./bb (self-contained PHAR)
make install        # build + install to ~/.local/bin/bb
make test           # run test suite
make demo           # build, install, record demo.gif (requires vhs)
make clean          # remove build artifacts
```

## Config

Credentials are stored at `~/.local/share/bb-cli/config.json` with `chmod 0600`.
Secret fields are encrypted at rest — switching machines requires re-running `bb auth`.

If you have an existing `~/.bitbucket-rest-cli-config.json` from a previous
install, bb will migrate it automatically on first run.

## Credits

Forked from [bb-cli/bb-cli](https://github.com/bb-cli/bb-cli), originally
created by [Celal Akyüz](https://github.com/cllakyz) and
[Dinçer Demircioğlu](https://github.com/dinncer), with contributions from
Erşan Işık, Semih Erdogan, and others. This fork adds API token support,
a git credential helper, a built-in installer, an overhauled help system,
and a Makefile-based build.

## License

MIT — see [LICENSE](LICENSE).
