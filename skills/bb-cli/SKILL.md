---
name: bb-cli
version: 1.1.0
description: >
  Use this skill whenever the user wants to interact with Bitbucket Cloud — PRs, branches,
  pipelines, deployment environments, auth, or browsing a repo. Covers all bb-cli commands:
  bb pr, bb branch, bb pipeline, bb env, bb auth, bb browse, bb git, bb install, bb upgrade.
  Trigger on phrases like "show me the PRs", "open the pipeline", "approve PR", "list branches",
  "browse the repo", "set up Bitbucket auth", "create a PR", etc. Don't wait for the user to
  say "use bb" — infer intent and run the right command.
---

# bb-cli Skill

`bb` is a PHP CLI tool for the Bitbucket Cloud REST API. It operates from a git repo directory
(with a `bitbucket.org` remote), or you can target any repo with `--project owner/repo`.

## Global Flags

| Flag | Description |
|------|-------------|
| `--project owner/repo` | Target a remote repo instead of the current directory |
| `--json` | Emit structured JSON — use this when you need to parse output or pass it to other tools |
| `--help` / `-h` | Help for the current command |
| `--version` / `-v` | Print version |

---

## Commands

### PR — Pull Request Management

Default subcommand: `list`

```
bb pr [list]               List open PRs (alias: l)
bb pr view <id>            Show PR detail — title, state, reviewers, link (alias: show)
bb pr diff <id>            Show PR diff (alias: d)
bb pr files <id>           List changed files in PR
bb pr comments <id>        List comments on a PR
bb pr commits <id>         List commits / checks (aliases: checks, c)
bb pr create <to> [from]   Create PR; defaults to current branch as source
bb pr approve <id>         Approve (0 = approve all open PRs) (alias: a)
bb pr no-approve <id>      Remove approval (alias: na)
bb pr request-changes <id> Request changes (alias: rc)
bb pr no-request-changes <id> Remove request-changes (alias: nrc)
bb pr decline <id>         Decline / close a PR (alias: close)
bb pr merge <id>           Merge a PR (alias: m)
```

### Branch

Default: `list`

```
bb branch [list]           List all branches (alias: l)
bb branch user <name>      Filter branches by commit author (alias: u)
bb branch name <str>       Filter branches by name (alias: n)
```

### Pipeline

Default: `latest`

```
bb pipeline [latest]       Show most recent pipeline
bb pipeline get <id>       Show pipeline details
bb pipeline wait [id]      Block until pipeline completes (defaults to latest)
bb pipeline run <branch>   Trigger default pipeline on branch
bb pipeline custom <branch> <name>  Run a named custom pipeline (alias: c)
```

### Env — Deployment Environment Variables

Default: `environments`

```
bb env [list]                          List environments (aliases: list, l)
bb env variables <env-uuid>            List variables for an environment (alias: v)
bb env create-variable <env> <k> <v>  Create a variable (alias: c)
bb env update-variable <env> <var> <k> <v>  Update a variable (alias: u)
```

### Auth — Manage Credentials

Default: `token`

```
bb auth token    Save API token credentials — prompts for email + token (recommended)
bb auth save     Save app password credentials (legacy)
bb auth show     Show current auth config
```

Auth is stored at `~/.local/share/bb-cli/config.json`.
API tokens use your Atlassian account email. App passwords use your Bitbucket username.
Both use HTTP Basic Auth: `base64(identity:secret)`.

### Browse

Default: `browse`

```
bb browse        Open repository in browser (alias: b)
bb browse url    Print repository URL (alias: show)
```

### Git — Credential Helper

```
bb git setup    Install git credential helper for bitbucket.org
bb git remove   Uninstall the credential helper
```

After `setup`, git operations against bitbucket.org auto-authenticate using stored credentials.

### Install / Upgrade

```
bb install    Copy bb binary to ~/.local/bin (or first writable PATH dir)
bb upgrade    Self-update: fetch and install latest GitHub release
```

---

## Tips for Using This Skill

**Run from the repo.** Most commands need a `bitbucket.org` git remote. From elsewhere, pass `--project owner/repo`.

**Parse output with --json.** When chaining commands or inspecting values, `bb pr list --json` returns clean structured data.

**PR IDs.** Most `bb pr` subcommands take a numeric ID. Use `bb pr list` (or `bb pr list --json`) to find the ID.

**Approve all open PRs.** `bb pr approve 0` approves every open PR at once.

**Wait on CI.** `bb pipeline wait` blocks until the latest pipeline finishes — useful in scripts.

**First-time setup.** Run `bb auth token` then `bb git setup` to fully configure auth including git operations.

---

## Common Patterns

Show PRs and view one:
```bash
bb pr list
bb pr view 42
```

Approve and merge after review:
```bash
bb pr approve 42
bb pr merge 42
```

Watch a pipeline:
```bash
bb pipeline run main
bb pipeline wait
```

Set up auth from scratch:
```bash
bb auth token
bb git setup
```

Inspect from outside a repo:
```bash
bb --project myorg/myrepo pr list --json
```
