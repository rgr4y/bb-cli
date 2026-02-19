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

- PHP 8.3+ with `curl`, `mbstring`, `json` extensions
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

Run `bb --help` for the full command reference. Key workflows:

```sh
# Pull requests
bb pr list                          # open PRs in current repo
bb pr create main                   # open PR from current branch → main
bb pr files 42                      # files changed in PR #42
bb pr approve 42                    # approve PR #42

# Branches
bb branch list                      # all branches
bb branch user rob                  # branches by author
bb branch name feat                 # branches matching "feat"

# Pipelines
bb pipeline latest                  # latest pipeline status
bb pipeline wait                    # block until pipeline finishes

# Work on a remote repo without cloning
bb pr list --project org/repo
```

## Commands

| Command    | Description                          |
|------------|--------------------------------------|
| `auth`     | Manage credentials                   |
| `branch`   | List and filter branches             |
| `pr`       | Pull request management              |
| `pipeline` | Pipeline status and control          |
| `env`      | Deployment environment variables     |
| `browse`   | Open repo in browser                 |
| `git`      | Git credential helper setup          |
| `install`  | Install bb to PATH                   |
| `upgrade`  | Self-update from latest release      |

Run `bb <command> --help` for subcommand details.

## Building

```sh
make build          # produces ./bb (self-contained PHAR)
make install        # build + install to ~/.local/bin/bb
make demo           # build, install, record demo.gif (requires vhs)
make clean          # remove build artifacts
```

## Config

Credentials are stored in `~/.local/share/bb-cli/config.json`. The file is
created on first `bb auth` run. Keep it out of version control.

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
