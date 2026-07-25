# Reinventx Forms — Architecture

This document records the architecture decisions for Reinventx Forms and, more importantly,
the reasoning behind them. Decisions here are binding until explicitly revisited.

---

## 1. Repository Strategy: Single-Plugin Repo (deliberately NOT a monorepo)

### The decision

One repository containing **one plugin**, with strong internal module boundaries.
No pnpm/Composer multi-package workspace, no `packages/` directory, no internal
package publishing.

### Why a multi-package monorepo is the wrong call today

A monorepo earns its cost when there are **multiple deliverables sharing code**:
free plugin + Pro plugin + SaaS backend + shared SDK. Reinventx Forms v0.1 has exactly
one deliverable. A workspace setup now would buy us:

- versioning ceremony between packages nobody else consumes,
- path-mapping and autoload gymnastics in every tool config,
- a build pipeline that assembles the plugin from fragments before every test run,

…and would buy us nothing in exchange. This is the classic over-engineering trap:
importing Google-shaped tooling for a product with zero users.

### Why not the other direction (multi-repo per concern)

Splitting admin UI / backend / build tooling into separate repos is even worse:
cross-repo PRs for every vertical feature, version drift, and CI that can't test
the actual product. Rejected without hesitation.

### Monorepo-ready conventions (the cheap insurance)

The plugin repo is structured so that a future `packages/` split is mechanical,
not a rewrite:

- All PHP under one root namespace (`Reinventx\`) with strict module subnamespaces.
  Modules do not reach into each other's internals — they talk through service
  interfaces. A module that stays clean can be lifted into a package.
- JS is split into independent entry points (`client/admin`, `client/public-form`)
  with no shared mutable state; shared TS utilities live in `client/shared`.
- The **extension surface is hooks + REST**, not classes. A future Pro plugin is a
  *separate plugin* consuming `do_action`/`apply_filters`/REST — the moment that
  exists, this repo becomes a two-repo product or converts to a workspace. Decide then.

## 2. Repository Structure

```
reinventx-forms/
├── reinventx-forms.php            # Bootstrap ONLY: header, constants, autoload, Plugin::boot()
├── uninstall.php            # Delegates to Uninstaller; respects "delete data" opt-in
├── composer.json            # PSR-4 autoload + dev tooling. No runtime deps.
├── package.json             # @wordpress/scripts + TS. Two build entry points.
├── phpcs.xml.dist, phpstan.neon.dist, tsconfig.json, .editorconfig
├── .wp-env.json             # Local dev environment (Docker)
├── .distignore              # What never ships in the release ZIP
├── src/                     # PHP — namespace Reinventx\
│   ├── Plugin.php           # Composition root: builds container, registers modules
│   ├── Support/             # Container (minimal), Hooks helper, shared value objects
│   ├── Database/            # Schema definitions, Migrator, table name registry
│   ├── Forms/               # Form entity, FormRepository, field type registry, validation
│   ├── Leads/               # Lead entity, LeadRepository, statuses, notes, context capture
│   ├── Rendering/           # Shortcode, block registration, form renderer (templates/)
│   ├── Http/                # REST controllers, request validation, permission callbacks
│   └── Admin/               # Menu, admin page, asset enqueueing, boot data for the SPA
├── templates/               # PHP view partials for the public form (escaped output only)
├── client/
│   ├── admin/               # React + TS SPA (forms editor, inbox, lead detail)
│   ├── public-form/         # Vanilla TS: fetch submit, inline validation UI (~few KB)
│   ├── blocks/rvtx-form/  # block.json + edit component (dynamic block)
│   └── shared/              # Types shared across entries (Form, Lead, API payloads)
├── build/                   # Compiled assets (gitignored; included in release ZIP)
├── tests/
│   ├── Unit/                # Pure PHP, no WordPress loaded
│   └── Integration/         # wp-env + WP test suite (REST, DB, activation)
├── docs/
└── .github/workflows/       # ci.yml, release.yml
```

Rules that keep this honest:

- `reinventx-forms.php` contains **no business logic** — it guards PHP/WP versions, loads
  the autoloader, and boots `Plugin`. Everything else lives in `src/`.
- `src/` modules register their own hooks via a shared registration pass in
  `Plugin::boot()`; no module calls `add_action` at file-load time.
- `templates/` is presentation only; every echo is escaped at the point of output.

## 3. Backend (PHP) Design

- **PHP 8.1 minimum.** 8.2/8.3 tested in CI. 8.1 keeps us on typed properties, enums,
  readonly, first-class callables, while matching what serious hosts actually run.
- **Composer for autoloading and dev tools only.** Bundling runtime libraries inside a
  WordPress plugin invites version conflicts with other plugins sharing the same
  process (the classic Guzzle-collision problem). If a runtime dependency ever becomes
  unavoidable, we introduce PHP-Scoper *then* — not speculatively now.
- **Composition root, minimal container.** `Plugin` wires concrete services in one
  place (a ~50-line service locator with lazy factories — not a DI framework).
  Constructor injection everywhere below it, so units are testable without WordPress.
- **Repositories over ORM.** `FormRepository` / `LeadRepository` wrap `$wpdb` with
  typed methods (`find`, `paginate`, `insert`, `updateStatus`). All SQL lives here,
  always through `$wpdb->prepare()`. No query builder dependency.
- **Entities as plain typed objects** hydrated from rows. Field config and lead
  payloads decode from JSON into typed structures at the repository boundary, so the
  rest of the code never touches raw arrays-of-mystery.
- **Field type registry:** each field type (text, email, textarea) is a small class
  implementing sanitize/validate/render. Adding a field type in 0.2+ means adding one
  class, not editing switch statements. This is the one piece of "framework" v0.1
  builds, because the product's core loop passes through it on every request.
- **Domain events via WP hooks:** `rvtx_lead_created`, `rvtx_lead_status_changed`,
  etc. These cost nothing now and are the entire future Pro/AI extension surface.

## 4. Frontend Design

### Admin SPA (`client/admin`)

- React + TypeScript, built by `@wordpress/scripts` (webpack, externals, i18n, RTL —
  solved problems we should not re-solve with a bespoke Vite setup that fights
  WordPress's script registration model).
- UI: **shadcn/ui + Tailwind (v4), vendored, brand-black theme** — not
  `@wordpress/components`. Reinventx Forms is a product with its own identity; the stock
  Gutenberg look was rejected (decision revised in M1.5, replacing the original
  `@wordpress/components` choice). shadcn components are copied into
  `client/admin/components/ui/` (they are source, not a dependency); their runtime
  deps (Radix primitives, class-variance-authority, clsx, tailwind-merge,
  lucide-react) are bundled into the **admin entry only**. Trade-offs accepted:
  the admin bundle is no longer near-zero (size-guarded in CI instead), and
  accessibility comes from Radix rather than WP components.
  Non-negotiable isolation rules:
  - Tailwind preflight and all utilities are **scoped to the `#rvtx-admin`
    container** — wp-admin chrome (admin bar, menu, notices) must be untouched.
  - Every Radix **portal mounts inside the container** (Dialog, Select, dropdowns),
    otherwise popovers render into `document.body` outside the scope and lose styling.
  - Tailwind/shadcn never enter `client/public-form`. The public bundle stays a
    framework-free enhancement script.
  - Theme tokens (shadcn CSS variables: primary black on neutral grays, visible
    focus rings, light-mode only) live in one file; components never hardcode colors.
- `@wordpress/api-fetch` for REST (nonce handling is automatic) and `@wordpress/i18n`
  for strings remain WP-core externals. Inside the block editor (M4), the form-picker
  edit component still uses `@wordpress/components` — matching Gutenberg there is
  correct; the SPA and the block editor are different surfaces.
- One admin page (`admin.php?page=reinventx-forms`) hosting the SPA with lightweight
  client-side routing (hash or memory router): Forms list → Form editor; Inbox →
  Lead detail. No react-router dependency unless routing outgrows ~5 screens.
- Server passes boot data (REST root, nonce, current user caps, statuses list) via
  `wp_add_inline_script` — no hardcoded URLs in JS.
- State: local component state + a thin fetch layer. **No Redux/Zustand/react-query
  in v0.1.** Introduce a data library when a real caching/invalidation problem exists.

### Public form (`client/public-form`)

The most important frontend decision: **the visitor-facing form ships no framework.**
The form is rendered server-side from stored config; a single small vanilla-TS script
progressively enhances it (fetch-based submit, inline error rendering, disabled-state
handling). If JS fails, the `<form>` still POSTs and gets a server-rendered response.
Marketing sites live and die by page weight; a React runtime to render three inputs
would be an embarrassment and a real competitive weakness.

### Block strategy

One **dynamic block** (`reinventx-forms/form`): `block.json`, an edit component that lets
the author pick a form (SelectControl fed by the REST forms endpoint, ServerSideRender
preview), and a `render_callback` that is **the same renderer the shortcode uses**.
No block-side markup serialization — form structure changes must never invalidate
saved post content. The shortcode `[rvtx_form id="…"]` exists for classic-editor and
page-builder users and costs one thin wrapper around the same renderer.

## 5. Database Design

Custom tables. Not custom post types. The reasoning:

- Leads are high-volume, filterable, sortable operational data. CPT + postmeta gives
  EAV queries, meta-table bloat, and no real typing. We would fight it forever.
- Forms *could* be a CPT, but splitting storage models between forms and leads buys
  a little free UI we don't want (we're building a React admin) at the cost of two
  persistence idioms. One idiom, owned fully.
- The cost we accept: we own install/migration/uninstall. That's Milestone 0 work
  and it's bounded.

```sql
{prefix}rvtx_forms
  id            BIGINT UNSIGNED AUTO_INCREMENT PK
  name          VARCHAR(190) NOT NULL
  status        VARCHAR(20)  NOT NULL DEFAULT 'active'    -- active|archived
  config        LONGTEXT     NOT NULL                     -- JSON: fields[], settings
  created_at    DATETIME     NOT NULL
  updated_at    DATETIME     NOT NULL
  KEY status (status)

{prefix}rvtx_leads
  id            BIGINT UNSIGNED AUTO_INCREMENT PK
  form_id       BIGINT UNSIGNED NOT NULL
  status        VARCHAR(20)  NOT NULL DEFAULT 'new'       -- new|contacted|qualified|won|lost|spam
  data          LONGTEXT     NOT NULL                     -- JSON: field values keyed by field id
  source_url    TEXT         NULL
  source_title  TEXT         NULL
  referrer_url  TEXT         NULL
  user_agent    VARCHAR(255) NULL
  ip_hash       VARCHAR(64)  NULL                         -- hashed; filterable off (GDPR)
  submitted_at  DATETIME     NOT NULL
  KEY form_status (form_id, status)
  KEY submitted_at (submitted_at)

{prefix}rvtx_lead_notes
  id            BIGINT UNSIGNED AUTO_INCREMENT PK
  lead_id       BIGINT UNSIGNED NOT NULL
  user_id       BIGINT UNSIGNED NOT NULL
  note          TEXT         NOT NULL
  created_at    DATETIME     NOT NULL
  KEY lead_id (lead_id)
```

Design notes:

- **Field values as JSON**, not an EAV `lead_values` table. v0.1 filters leads by
  form and status — indexed columns. Per-field querying/reporting is a future
  feature; when it arrives, we add a projection table for the fields that need it,
  driven by a real requirement. EAV-by-default is how form plugins get slow.
- **Statuses are strings, not DB enums** — configurable statuses later become a
  settings feature, not a migration.
- **Form config is versioned JSON** (`{"version":1,"fields":[…]}`). Field *ids* are
  stable keys, so renaming a field label never orphans historical lead data.
- **No foreign key constraints** (WordPress/dbDelta convention; MyISAM still exists
  in the wild). Referential integrity enforced in repositories; deletes cascade in
  application code inside a transaction where available.
- **Migrations:** schema version stored in an option (`rvtx_schema_version`).
  `Migrator` runs ordered migration steps on activation and on version bump
  (admin-triggered on upgrade, never on the public path). dbDelta for table
  creation; explicit SQL for later alters.

## 6. REST API Design

Namespace: **`reinventx-forms/v1`** — versioned from day one because a future Pro plugin
and SaaS bridge will consume it; breaking it must be a deliberate `v2`.

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/forms` | GET/POST | cap: `rvtx_manage_forms` | List / create forms |
| `/forms/{id}` | GET/PUT/DELETE | cap: `rvtx_manage_forms` | Read / update / archive |
| `/leads` | GET | cap: `rvtx_manage_leads` | Paginated inbox; filters: form_id, status (search is post-0.1 backlog) |
| `/leads/{id}` | GET | cap: `rvtx_manage_leads` | Lead detail incl. notes |
| `/leads/{id}` | PATCH | cap: `rvtx_manage_leads` | Update status |
| `/leads/{id}/notes` | POST | cap: `rvtx_manage_leads` | Add note |
| `/submissions` | POST | **public** | Visitor submission (hardened, see §7) |

- Admin routes authenticate via WordPress cookie + `X-WP-Nonce` (what `api-fetch`
  does automatically). Every route has an explicit `permission_callback` — never
  `__return_true` except the public submission route, where "public" is the point.
- Request schemas declared via REST `args` with `validate_callback`/`sanitize_callback`,
  then domain-level validation in the service layer. Two layers on purpose: transport
  shape vs. business rules.
- Responses are stable DTO shapes defined in one place per resource (shared TS types
  in `client/shared` mirror them).
- Errors: `WP_Error` with meaningful codes (`rvtx_invalid_field`,
  `rvtx_rate_limited`) — the public form script maps codes to inline messages.

## 7. Security Model

- **Capabilities:** custom caps `rvtx_manage_forms`, `rvtx_manage_leads`,
  granted to `administrator` on activation. Cheap now, painful to retrofit, and it
  lets 0.2+ offer role-based access (e.g., a "sales" role that sees the inbox but
  can't edit forms).
- **Input:** every field value passes its field type's `sanitize()` then `validate()`.
  Unknown field keys in a submission are dropped, not stored. REST args validated at
  the transport layer as well.
- **SQL:** all queries through `$wpdb->prepare()`; table names from a single
  registry; no string-interpolated SQL anywhere. PHPCS's WordPress security sniffs
  enforce this in CI.
- **Output:** all template output escaped at point of use (`esc_html`, `esc_attr`,
  `esc_url`); lead data rendered in the React admin is text-content by default
  (React escapes), and we never `dangerouslySetInnerHTML` lead-supplied data.
  Lead payloads are treated as **hostile input forever** — they get re-escaped at
  every future output surface (emails, exports, AI prompts).
- **Public submission endpoint** (no nonce — visitors are unauthenticated, and
  page-cached nonces expire; this is the standard, correct trade-off). Compensating
  controls, all server-side:
  - honeypot field (dropped from stored data, rejects on fill),
  - minimum-fill-time token (signed timestamp rendered with the form; too-fast = reject),
  - per-IP rate limiting via transients (threshold filterable via `rvtx_rate_limit_max`),
  - per-field length caps enforced by the field types; input keys not defined
    by the form are discarded, so accepted payloads are bounded by the form itself,
  - strict content-type check on the REST path.
    (A same-origin `Origin`/`Referer` soft check was considered and is **not
    shipped in v0.1** — privacy tools strip those headers, so it adds noise
    before it adds signal. Revisit post-0.1 if abuse patterns warrant it.)
- **Context capture** (page URL, title, referrer) is *client-reported and untrusted*:
  sanitized as URLs/text, stored, and always displayed escaped. It's lead intelligence,
  not audit data.
- **Privacy:** IP stored as salted hash only, filterable to off via
  `rvtx_store_ip_hash` (disabling it also disables per-client rate
  limiting, which keys on the hash); user agent truncated.
  Uninstall can fully purge. This posture keeps GDPR conversations short.

## 8. Testing Strategy

Layered by cost, not by ideology:

1. **Unit (PHPUnit, no WordPress):** field type sanitize/validate, config
   serialization, status transitions, rate-limit math. Fast, run on every push.
   This is why services take dependencies via constructor — most logic tests
   without loading WP.
2. **Integration (PHPUnit + wp-env + WP test suite):** activation creates tables;
   migrations are idempotent; every REST route's auth, validation, and happy path;
   repository SQL against real MySQL; shortcode/block render output.
3. **Static:** PHPStan (level 6 to start, with WordPress stubs) and PHPCS
   (WordPress ruleset incl. security sniffs). TypeScript `strict: true`,
   ESLint via wp-scripts.
4. **E2E (Playwright): deferred to post-0.1.** One manual smoke script
   (documented checklist) gates each release until then. Writing E2E before the
   admin UI stabilizes means rewriting E2E; that's waste, not rigor.

Coverage target: none enforced. Enforced instead: every REST route and every field
type has integration tests; every bug fix lands with a regression test.

## 9. DevOps & Release

- **Local dev:** `wp-env` — `pnpm run env:start` gives WordPress + MySQL in Docker
  with the plugin mounted. Same image family CI uses. No bespoke Docker compose.
- **CI (GitHub Actions, on PR + main):**
  1. PHP job: composer validate, PHPCS, PHPStan, unit tests — matrix PHP 8.1/8.3.
  2. Integration job: wp-env test suite against latest WordPress. (A WP
     latest/latest−1 matrix is planned post-0.1; wp-env pins would need
     per-release maintenance and v0.1 targets 6.6+.)
  3. JS job: typecheck, lint, build (build must succeed on every PR).
- **Release (tag-triggered `release.yml`):**
  1. Tag `vX.Y.Z` → verify tag matches plugin header + readme stable tag.
  2. `composer install --no-dev --optimize-autoloader` (autoloader ships; dev deps never do).
  3. `pnpm install --frozen-lockfile && pnpm run build`.
  4. Assemble ZIP honoring `.distignore` (no `client/` sources, tests, configs, CI files).
  5. Smoke-check the ZIP: install & activate in a clean wp-env container.
  6. Attach ZIP to a GitHub Release. (WordPress.org SVN deploy step added if/when we list there.)
- **Versioning:** SemVer. Plugin header, `REINVENTX_VERSION` constant, and readme
  updated by a small release script so they can never drift.

## 10. Future Extensibility (designed for, not built)

The rule: **v0.1 ships seams, not features.** Every future direction below maps to
an existing seam, so none requires re-architecture:

| Future | Seam that already exists in v0.1 |
|---|---|
| Pro plugin (paid features) | Separate plugin consuming hooks + REST; base plugin never contains license code |
| Email notifications / automation | `rvtx_lead_created` / `_status_changed` actions |
| New field types (select, file, phone…) | Field type registry — one class per type |
| AI lead scoring / summaries | Lead JSON payload + notes + a future `lead_meta` table; events to trigger enrichment |
| SaaS sync / mobile app | Versioned REST API with stable DTOs |
| Configurable statuses & pipelines | Statuses are strings + a settings seam, not schema |
| Per-field reporting | Projection table added by a migration when the feature is real |
| Role-based team access | Custom capabilities already separate forms vs. leads |

What we deliberately do **not** build now: an addon framework, a settings framework,
an abstraction over "storage backends," or interfaces with a single implementation.
Each of those is speculative generality — the seams above are enough.
