# Contributing to tangentopay-php

Thank you for your interest in contributing. This document covers development setup, conventions, and how releases are made.

---

## Table of contents

- [Development setup](#development-setup)
- [Running tests](#running-tests)
- [Code style](#code-style)
- [Branch naming](#branch-naming)
- [Commit messages](#commit-messages)
- [Workflow](#workflow)
- [Releasing a new version](#releasing-a-new-version)

---

## Development setup

```bash
git clone https://github.com/Grut-Design-Agency/tangentopay-php.git
cd tangentopay-php
composer install
```

PHP 8.1 or higher is required.

---

## Running tests

```bash
# Run the full suite
vendor/bin/phpunit

# Run with verbose output
vendor/bin/phpunit --testdox

# Run a specific test file
vendor/bin/phpunit tests/WebhookTest.php
```

The test suite uses PHPUnit with Guzzle's `MockHandler` — no real network requests are made and no API keys are needed.

All tests must pass before a pull request can be merged. CI runs the full suite on PHP 8.1, 8.2, and 8.3 on every push.

---

## Code style

The project uses PHP 8.1+ features (readonly properties, named arguments, match expressions, enums where appropriate) and strict types on every file.

```php
declare(strict_types=1);
```

Line length is capped at 120 characters. Type-hint everything — parameters, return types, and properties.

---

## Branch naming

| Type | Pattern | Example |
|---|---|---|
| New feature | `feat/<short-description>` | `feat/retry-logic` |
| Bug fix | `fix/<short-description>` | `fix/webhook-hex-validation` |
| Documentation | `docs/<short-description>` | `docs/laravel-example` |
| Refactor | `refactor/<short-description>` | `refactor/http-client` |
| Tests | `test/<short-description>` | `test/analytics-coverage` |
| CI / tooling | `ci/<short-description>` | `ci/php83-matrix` |
| Chore / maintenance | `chore/<short-description>` | `chore/bump-guzzle` |
| Hotfix | `hotfix/<short-description>` | `hotfix/signature-crash` |
| Release preparation | `release/<version>` | `release/0.2.0` |

**Rules:** all lowercase, hyphens only, 2–4 words, branch from `main`.

---

## Commit messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short summary>
```

| Type | When to use |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `test` | Tests only |
| `refactor` | No behaviour change |
| `chore` | Dependencies, tooling, CI |
| `security` | Security fix |

---

## Workflow

1. Fork (external) or branch directly (team)
2. Follow the branch naming convention
3. Write tests for all new behaviour; bugfixes need a regression test
4. Run `vendor/bin/phpunit` locally before pushing
5. Open a pull request against `main`
6. All CI checks must pass before merge
7. Squash and merge for small changes; merge commit for larger features

---

## Releasing a new version

Releases are published to [Packagist](https://packagist.org/packages/tangentopay/tangentopay-php) automatically when a version tag is pushed to `main`. Only maintainers with push access to `main` can trigger a release.

**Steps:**

1. Bump the version in `composer.json` and `src/TangentoPay.php`:
   ```bash
   # Edit composer.json "version" field
   # Edit src/TangentoPay.php VERSION constant
   ```

2. Commit the version bump:
   ```bash
   git add composer.json src/TangentoPay.php
   git commit -m "chore: bump version to 0.2.0"
   ```

3. Tag and push:
   ```bash
   git tag v0.2.0
   git push origin main
   git push --tags
   ```

4. GitHub Actions runs the full test suite and then notifies Packagist via the `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` secrets in the `packagist` environment. Packagist fetches the new tag and publishes the release automatically.

> **Packagist setup:** The `packagist` environment requires `PACKAGIST_USERNAME` (your Packagist username) and `PACKAGIST_TOKEN` (an API token from your Packagist profile). Add these under **Settings → Environments → packagist** in the GitHub repository.
