# AGENTS.md

Shared instructions for coding agents. Project-specific information is kept in [README.md](README.md), read it before non-trivial changes.

## Farah Module

This project is a Farah module. Its module assets live in `assets/`, with `assets/manifest.xml` as the public entry point. Treat published asset paths, manifest identifiers, parameters, and stream names as public API. Preserve them unless the task explicitly permits a breaking release.

Keep manifests, schemas, PHP registration code, fixtures, and documentation synchronized when the module contract changes. Test both lookup and the resolved result: a manifest entry existing is not sufficient if it produces the wrong stream, media type, transformation, or content.

## PHP

This project utilizes DDEV. Start its runtime with `ddev start`, then install its dependencies with `ddev composer install --no-interaction --no-progress`.

Development targets the DDEV runtime. Omit `ddev exec` only when specifically debugging host OS behavior.

Code must be syntactically valid on the PHP version declared by `PHP_VERSION` in `.env`.

### Testing

Test code with `ddev exec php vendor/bin/phpunit --colors=never`.

Maintain focused tests for public behavior and discovered regressions.

If an optional extension or platform feature is unavailable in DDEV, report which validation could not run and why. Treat skipped tests as intentional unless the task concerns test skipping.

Use `@runInSeparateProcess` for tests of APIs with global or static process state. When manually changing a test marked `@todo auto-generated`, remove the marker so the generator no longer treats it as disposable. Files in `test-files/` are canonical fixtures, not disposable output.

### Linting

Format changed files with PhpStorm MCP `reformat_file` and `lint_files` to follow `.editorconfig`.

Perform static analysis with `ddev exec php vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --error-format=json`.

Changed code must pass linting without a change in baseline.

### Documentation

Documentation is built automatically by GitHub CI and hosted on GitHub Pages.

## General

### Meta commands

These short messages have special handling when they appear alone in a user message:

- `ping`: Reply with `pong`.
- `?`: Continue the previous response or task after an interruption.
- `ticket <URI>`: Read the linked ticket and all comments through the available integration. Inspect the project, reproduce the current behavior, and run relevant checks as needed. Then explain the request, project context, reproducibility, risks, and a proposed implementation plan. Do not edit files, change remote state, commit, or push until the user approves the approach.

### Compatibility

Follow semantic versioning. Preserve backward compatibility for public APIs unless the task explicitly permits a breaking change.

### Project conventions

`.editorconfig` is authoritative. Never edit `.editorconfig` unless expressly instructed by the user.

### Git

Git mutations are forbidden by default. Agents may use read-only inspection commands such as `git status`, `git log`, `git diff`, `git show`, `git blame`, and `git branch --list` without additional permission.

An agent may perform Git mutations only after the user explicitly opts in. Permission is limited to the operations and task the user authorized; do not treat prior authorization as standing permission for later mutations.

When Git mutations are authorized:

- Do not change the current branch unless asked. Verify the working-tree status before editing and again before creating commits.
- Treat all unknown local changes as user work. Do not overwrite, stage, commit, restore, or otherwise alter them unless they complement your task.
- Keep commits small and cohesive.
- Format agent-authored commits according to Conventional Commits 1.0.0: `<type>[optional scope]: <description>`.
- When working from a ticket, include the ticket key and URL in the commit footer.
- Before committing, read the configured Git author name and email. Keep the configured email, append the agent name once, in brackets to the configured author name (e.g. `Daniel Schulz (Codex)`), and pass that identity explicitly with `git commit --author`. Do not modify repository or global Git configuration.
- Do not force-push, amend, rebase, reset, or discard changes unless the user explicitly requests that specific operation.
- Rewording commits not yet pushed to any remote is permitted.

### CI Infrastructure

Jenkins runs at `https://ci.slothsoft.net/`. Infrastructure reads are always permitted; triggering builds or making any other change requires explicit user consent.

Use these access methods:

- `ssh ci.slothsoft.net <command>` invokes the Jenkins CLI directly; it does not open a host shell. Useful read-only commands include `who-am-i`, `list-jobs`, and `console`.
- `ssh <host>` opens that server's shell.
- `docker --context <host> ...` talks to that server's Docker daemon. Always name the context explicitly.

The CI servers are:

- `groke`: Mörkö, Ubuntu; Jenkins controller and Swarm manager. SSH alias and Docker context: `groke`.
- `garl`: Garl, Ubuntu; Jenkins agent and Swarm worker for `groke`. SSH alias and Docker context: `garl`.
- `dende`: Dende, Windows Server 2019; domain controller and Hyper-V host for `popo`. SSH alias and Docker context: `dende`.
- `popo`: Mr. Popo, Windows Server 2019 Standard Core VM on `dende`; Jenkins agent and Swarm worker for `groke`. SSH alias and Docker context: `popo`.

## Composer Package

This project is a Composer package.

Keep cross-platform and supported-PHP integration coverage in `.jenkins/Jenkinsfile.groovy`. The project job lives under `https://ci.slothsoft.net/job/php-packages/` and matches the repository name. GitHub CI validates the supported PHP and dependency matrix; Jenkins validates the repository's Linux and Windows integration matrix. For either system, inspect the result for the exact commit and read the complete relevant logs.

### Release cycle

When the user has authorized a release, complete every phase in order.

#### Phase 1: Local work

1. Implement the change, add or update its tests, and document user-visible behavior under `Unreleased` in `CHANGELOG.md`.
2. Run focused tests while developing, then run the complete local test suite and required linting checks.
3. Local work may be committed as needed. Keep its changelog entries under `Unreleased` until preparing the release candidate.

#### Phase 2: Release candidate and CI

1. Select the next version according to semantic versioning. If a new development line begins, keep Composer's branch alias aligned with it.
2. Finalize all release metadata, including moving the relevant `CHANGELOG.md` entries from `Unreleased` into a heading for the selected version and release date. Update changelog comparison links.
3. Run any local checks required by the release preparation changes, then commit and push the release candidate. The pushed commit must already be ready to tag as the selected version.
4. Watch the complete GitHub CI run and the matching job under `https://ci.slothsoft.net/job/php-packages/` for that exact commit, and read the complete relevant logs.
5. If either validation fails, restart at phase 1, step 1. Keep the selected version unless the new changes demand a higher-order semantic version.

#### Phase 3: Tag and close out

1. After both CI systems pass, tag the exact validated commit using the repository's existing version-tag format, and push the tag. Never reuse or move a published tag.
2. Update any associated issues and pull requests to reflect the shipped design and release version, and mark completed work as complete.

If the expected behavior or its test contract changes at any point, restart at Phase 1, step 1.
