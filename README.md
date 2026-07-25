# Reinventx Forms

Standalone form and lead management plugin for WordPress — create forms, capture
leads with source context, and track follow-up status. No dependency on any other
form plugin.

**Status:** v0.1.0 is feature-complete and in final QA — **no release has been
tagged yet**. The first tag will ship everything below.
Planning docs: [`docs/PROJECT_PLAN.md`](docs/PROJECT_PLAN.md), [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md),
[`docs/MILESTONES.md`](docs/MILESTONES.md), [`docs/RELEASING.md`](docs/RELEASING.md).

## What Reinventx Forms does

- Form builder (text, email, paragraph fields) in a React admin at **wp-admin → Reinventx Forms**
- Embedding via the **Reinventx Form block** or the `[rvtx_form id="…"]` shortcode
  (one shared server-side renderer — identical output)
- Public pages load **zero React**: server-rendered form + a ~2 KB enhancement
  script; submissions work with JavaScript disabled
- Spam guards: honeypot, signed time-trap token (cache-safe nonce replacement),
  per-client rate limiting — all server-side, no external service
- Lead **inbox** with form/status filters and pagination; lead detail with source
  context (page URL, title, referrer), status pipeline
  (new / contacted / qualified / won / lost / spam), and notes
- Privacy: raw IPs are never stored (keyed hash only, or nothing at all via the
  `rvtx_store_ip_hash` filter); data survives uninstall unless the
  delete-data setting is enabled

## Requirements (host machine)

- Node.js 20+ and npm
- Composer 2 (PHP 8.1+ on the host, or run Composer via Docker — see below)
- Docker (for the `wp-env` local WordPress environment)

## Setup

```bash
composer install
pnpm install
pnpm run build        # compile admin + public + block bundles into build/
pnpm run env:start    # start WordPress at http://localhost:8888 and activate Reinventx Forms
```

Log in at `http://localhost:8888/wp-admin` — username `admin`, password `password`.

No Composer on the host? Use the Docker image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
```

> **Warning — never delete the plugin from the WP dashboard.** The dev site
> bind-mounts this source directory as the plugin folder; dashboard deletion
> would wipe your working copy. `.wp-env.json` sets `DISALLOW_FILE_MODS` to
> block this, so plugin/theme install/update/delete via the dashboard is
> intentionally disabled in dev. Use `wp plugin uninstall --skip-delete` for
> uninstall testing.

## Development commands

| Command | What it does |
|---|---|
| `pnpm run start` | Watch-mode JS build |
| `pnpm run typecheck` | TypeScript type checking |
| `pnpm run lint:js` | ESLint (WordPress config) |
| `composer lint` | PHPCS (WordPress security + best-practice sniffs) |
| `composer stan` | PHPStan static analysis (level 8, WordPress stubs) |
| `composer test:unit` | PHP unit tests (no WordPress loaded) |
| `pnpm run test:php` | Integration tests against real WordPress + MySQL (wp-env must be running) |
| `composer check` | lint + stan + unit tests in one go |
| `pnpm run env:stop` | Stop the local environment |

## Trying it with fixture data

```bash
npx wp-env run cli wp eval-file wp-content/plugins/reinventx-forms/bin/seed.php
```

creates a "Contact us" form and 35 leads across statuses — enough to exercise
inbox filtering and pagination. Embed the form on a page with the Reinventx Forms
Form block or `[rvtx_form id="…"]` and submit as a logged-out visitor.

## Releasing

Tagging `vX.Y.Z` triggers `release.yml`: version-sync check, production build,
`.distignore`-based ZIP with leak checks, clean-WordPress install smoke test,
then a GitHub Release. Steps and the manual QA checklist live in
[`docs/RELEASING.md`](docs/RELEASING.md).

## Project layout

```
reinventx-forms.php        Bootstrap only (header, guards, autoload, Plugin::boot)
src/                 PHP, PSR-4 namespace Reinventx\
client/              TypeScript sources (admin SPA, public form script, block editor)
blocks/              block.json metadata (server-registered)
build/               Compiled assets (generated; not committed)
bin/                 Dev/release scripts (seeding, version check, ZIP build)
tests/Unit           Pure PHP tests, WordPress never loaded
tests/Integration    WordPress test suite via wp-env
```

## License

GPL-2.0-or-later.
