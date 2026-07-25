# Reinventx Forms — Milestones to v0.1.0

Rules for every milestone:

- Merges to `main` only when CI is green.
- Each milestone is **independently demonstrable** — its acceptance criteria can be
  verified on a clean `wp-env` site without work from later milestones.
- Scope creep goes to the backlog, not into the branch.

---

## v0.1 Completion Report

| Milestone | Status |
|---|---|
| M0 — Foundation | **Complete** |
| M1 — Forms (admin vertical) | **Complete** |
| M1.5 — Admin design system (shadcn/ui + Tailwind) | **Complete** |
| M2 — Capture (public vertical) | **Complete** |
| M3 — Inbox (leads, status, notes) | **Complete** |
| M4 — Ship (block, hardening, release pipeline) | **Complete** |
| Post-review fixes (`spam` status, `rvtx_store_ip_hash`, `rvtx_rate_limit_max`) | **Complete** — ship with v0.1.0 |

The v0.1.0 tag has not been created yet: it follows the final QA pass
(`docs/RELEASING.md`), and the tag-triggered pipeline publishes the release.

Verification note: every criterion below is backed by automated coverage
(unit/integration tests, CI bundle guards, or the release workflow's
clean-install smoke test). Criteria that are inherently visual/manual —
no console errors, style isolation, keyboard-only editing, the full
in-browser demo — have automated equivalents and additionally remain on
the recurring manual checklist in `docs/RELEASING.md`, which should be
run against each released ZIP.

---

## M0 — Foundation (no user-visible features)

Repo scaffold, tooling, and the install/upgrade machinery everything else stands on.

**Work:**
- Repo init, plugin skeleton: `reinventx-forms.php` (header, version/PHP/WP guards,
  autoload, `Plugin::boot()`), `uninstall.php` stub.
- Composer: PSR-4 `Reinventx\ => src/`, dev deps (PHPUnit, PHPStan + WP stubs,
  PHPCS + WordPress ruleset).
- npm: `@wordpress/scripts`, TypeScript strict config, two entry points stubbed
  (`admin`, `public-form`).
- `wp-env` local environment; README with the 3-command onboarding.
- `Database\Migrator` + schema v1: the three tables from ARCHITECTURE §5.
- Activation: run migrations, register capabilities on `administrator`.
  Deactivation: no-op (data stays). Uninstall: delete tables/options **only if**
  the delete-data option is set.
- GitHub Actions `ci.yml`: PHP lint/stan/unit + integration + JS build, per
  ARCHITECTURE §9.

**Acceptance criteria:**
- [x] `pnpm run env:start && composer install && pnpm run build` yields a working local site.
- [x] Activating the plugin creates all three tables; `rvtx_schema_version` option = 1.
- [x] Re-activating is idempotent (no errors, no duplicate work).
- [x] Deactivate + reactivate preserves data; uninstall without opt-in preserves data;
      uninstall with opt-in removes tables and options.
- [x] Integration test proves table creation; unit test suite runs without WordPress.
- [x] CI passes on a PR; a deliberately broken PHPCS/PHPStan violation fails CI.

---

## M1 — Forms: create and manage (admin vertical)

First user-visible slice: an admin can create a form. No public rendering yet.

**Work:**
- `Forms` module: `Form` entity, `FormRepository`, config JSON (de)serialization
  with version field; field type registry with `text`, `email`, `textarea`
  (label, required flag, stable field ids).
- REST: `GET/POST /forms`, `GET/PUT/DELETE /forms/{id}` with permission callbacks,
  arg schemas, DTO responses.
- Admin: menu page, asset enqueueing, boot data inline script; React SPA shell with
  routing; **Forms list** screen and **Form editor** screen (name, add/remove/edit
  fields, required toggle, save via `api-fetch`).

**Acceptance criteria:**
- [x] "Reinventx Forms" menu appears for administrators only; SPA loads with no console errors.
- [x] Admin can create a form with the three field types, edit it, and see it in the list.
- [x] Deleting archives the form (status change), not a hard delete.
- [x] REST: unauthenticated and non-privileged requests to form routes get 401/403
      (integration-tested); invalid payloads get 400 with useful error codes.
- [x] Form config round-trips: what the editor saves is exactly what it loads.
- [x] Editor is usable with keyboard only (baseline a11y).

---

## M1.5 — Admin design system: shadcn/ui + Tailwind (brand pass)

Replace the placeholder wp-admin styling with Reinventx Forms's own design system —
shadcn/ui on Tailwind, primary color black — so every screen from here on (M3's
inbox is the big one) is built on the final primitives. Visual pass only: no new
features, no behavior changes. Decision rationale and isolation rules in
ARCHITECTURE §4.

**Work:**
- Toolchain: Tailwind v4 through the wp-scripts PostCSS pipeline; `@/` import alias
  (tsconfig `paths` + webpack alias) so vendored shadcn imports resolve; Tailwind
  content sources limited to `client/admin`.
- Vendor the needed shadcn/ui components into `client/admin/components/ui/` with
  their runtime deps (Radix primitives, class-variance-authority, clsx,
  tailwind-merge, lucide-react) bundled into the admin entry. Minimal kit:
  Button, Input, Select, Switch, Table, Card, Badge, Tabs, Dialog, Alert.
- Theme: shadcn CSS variables in one theme file — primary black on a neutral gray
  scale, white surfaces, visible focus rings, light mode only (wp-admin has no
  dark mode). Components consume tokens; no hardcoded colors.
- Style isolation: Tailwind preflight/utilities scoped to the `#rvtx-admin`
  container; every Radix portal (Dialog, Select popovers) mounts inside the
  container so overlays stay styled and wp-admin chrome stays untouched.
- Restyle the two existing screens on the new kit: Forms list (Tabs for
  active/archived, Table, Badge for status, Dialog for archive confirm) and Form
  editor (Card per field row, Switch for required, Alert for validation errors).

**Acceptance criteria:**
- [x] wp-admin outside the container is visually unchanged while the plugin page is
      open (admin bar, menu, notices) — no reset/utility leakage in either direction.
- [x] Dialogs and select popovers render fully styled (portal scoping proven).
- [x] All M1 acceptance criteria still pass, including keyboard-only editing —
      focus states must be clearly visible on the black/neutral theme.
- [x] `public-form.js` is byte-identical: the design system is admin-only.
- [x] Bundle guardrails in CI: `admin.js` ≤ 150 KB minified, admin CSS ≤ 50 KB.
      (Raised to 175 KB in M3 when the inbox screens landed; the public
      bundle budget is the one that never moves.)
- [x] Theme tokens centralized; changing the primary color is a one-file edit.

---

## M2 — Capture: render, submit, store (public vertical)

The product becomes real: a visitor can submit a form and it lands in the database.

**Work:**
- `Rendering` module: server-side renderer from form config (escaped template
  partials), shortcode `[rvtx_form id="…"]`; per-field HTML with labels, required
  attributes, error placeholders; hidden context inputs (page URL/title) + honeypot
  + signed timestamp token.
- `public-form` TS script: intercept submit, POST JSON to REST, render inline
  field errors / success message; no-JS fallback path returns a server-rendered result.
- REST `POST /submissions`: content-type checks, rate limiting, honeypot/time-trap,
  per-field sanitize→validate via the field registry, context capture (URL, title,
  referrer, UA, IP hash), persist lead, fire `rvtx_lead_created`.

**Acceptance criteria:**
- [x] Shortcode on a page renders the form; page loads **zero React** and one small
      script (bundle size recorded in the PR).
- [x] Valid submission stores a lead with correct field data and context
      (source URL, title, referrer, form id, timestamp) — integration-tested.
- [x] Invalid submission (missing required, bad email) returns field-level errors
      shown inline; nothing is stored.
- [x] With JS disabled, the form still submits and the visitor sees a result.
- [x] Honeypot-filled and too-fast submissions are rejected; N rapid submissions
      from one IP hit the rate limit (integration-tested).
- [x] Submitted `<script>` payloads are stored inert and never rendered unescaped.

---

## M3 — Inbox: leads, status, notes (the product promise)

**Work:**
- `Leads` module completion: `LeadRepository` pagination/filtering, status
  transition service (fires `rvtx_lead_status_changed`), notes.
- REST: `GET /leads` (paginate; filter by form, status), `GET /leads/{id}`,
  `PATCH /leads/{id}` (status), `POST /leads/{id}/notes`.
- Admin SPA: **Inbox** screen (table: name-ish primary field, form, status badge,
  date; filters; pagination) and **Lead detail** screen (all fields, context block,
  status control, notes timeline with author + time).

**Acceptance criteria:**
- [x] Inbox lists leads newest-first; filtering by form and by status works; pagination
      works past one page (seed script provides fixture leads).
- [x] Lead detail shows every submitted field plus source URL, page title, referrer,
      and submission time.
- [x] Status change persists, is reflected in the inbox list, and fires its action
      (integration-tested).
- [x] Notes save with current user attribution and render in order.
- [x] All lead routes deny non-privileged users (integration-tested).
- [x] A lead containing hostile HTML in every field displays safely everywhere.

---

## M4 — Ship: block, hardening, release (v0.1.0)

**Work:**
- Dynamic Gutenberg block: `block.json`, editor component (form picker +
  ServerSideRender preview), shared render callback with the shortcode.
- Settings toggle: "Delete all data on uninstall" (default off); wire `uninstall.php`.
- Hardening pass: PHPStan level bump if cheap, review every output escape, review
  all permission callbacks, confirm i18n coverage (text domain on all strings).
- `release.yml`: tag-triggered ZIP build per ARCHITECTURE §9, including the
  clean-install smoke check; `readme.txt`; version-sync release script.
- Manual smoke-test checklist documented in `docs/RELEASING.md`.

**Acceptance criteria:**
- [x] Block inserts in the editor, previews the real form, renders identically to the
      shortcode on the frontend; deleting a form leaves a graceful placeholder, not a fatal.
- [x] Tagging `v0.1.0` produces a ZIP that installs and activates on a clean WordPress
      (verified automatically in the workflow).
- [x] ZIP contains no `client/` sources, tests, lockfiles, or CI config; contains
      built assets and the optimized autoloader.
- [x] Full vertical demo passes on a clean site: create form → embed (block and
      shortcode) → submit as visitor → lead in inbox with context → status → note.
- [x] Uninstall respects the data-deletion setting in both positions.

---

## Post-0.1 backlog (ordered candidates — not commitments)

1. Email notification on new lead (`wp_mail` on `rvtx_lead_created`).
2. Playwright E2E covering the M4 demo path.
3. Field types: select, checkbox, phone, hidden.
4. Inbox search + bulk status actions.
5. CSV export of leads.
6. Configurable statuses.
