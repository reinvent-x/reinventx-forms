# Reinventx Forms — Project Plan

**Status:** v0.1 feature-complete; v0.1.0 release pending final QA · **Version target:** 0.1.0 · **Plan date:** 2026-07-02

---

## 1. Product Goal

Reinventx Forms is a **standalone form and lead management plugin** for WordPress. It owns the
entire flow — form creation, rendering, submission handling, lead storage, and follow-up
tracking — with **zero dependency on any other form plugin**.

**The v0.1 promise:**

> A business owner can create a form, publish it on a WordPress page, receive leads,
> understand where each lead came from, and track follow-up status.

The differentiator is not the form builder (the market has plenty). It is the **inbox**:
treating submissions as *leads with a lifecycle* (source context, status, notes) rather
than rows in an email pipe. v0.1 must nail that framing even in its smallest form.

## 2. Engineering Goal

Build a plugin that a small team can maintain for years:

- Modular, namespaced, PSR-4 PHP that is testable outside of WordPress where possible.
- A React/TypeScript admin that uses WordPress's own JS platform instead of fighting it.
- A public form that is deliberately **lightweight** — server-rendered PHP + a small
  vanilla-TS enhancement script. No React on the visitor-facing side.
- Custom database tables, owned migrations, and a versioned REST API.
- CI that blocks bad merges and a release pipeline that produces a clean ZIP on tag.

## 3. MVP Scope (v0.1 — one complete vertical slice)

- [ ] Plugin installs and activates cleanly; custom tables created; schema versioned.
- [ ] Admin menu "Reinventx Forms" exists; React admin app loads.
- [ ] Admin can create a basic form (name + fields: text, email, textarea; required flag).
- [ ] Form renders on any page via shortcode `[rvtx_form id="…"]` and a dynamic
      Gutenberg block (both share one server-side render pipeline).
- [ ] Visitor can submit the form (AJAX with no-JS `<form>` POST semantics preserved).
- [ ] Submission is validated (server-side, per field type), sanitized, and stored.
- [ ] Submission captures context: page URL, page title, referrer, form ID/name,
      submission timestamp, user agent.
- [ ] Basic spam resistance: honeypot field + minimum-fill-time check + per-IP rate limit.
- [ ] Admin sees a Lead Inbox: list leads, open a lead, see all fields + context.
- [ ] Admin can change lead status (New → Contacted → Qualified → Won / Lost / Spam).
- [ ] Admin can add internal notes to a lead (author + timestamp).
- [ ] Uninstall cleanly removes data **only if the user opts in** to data deletion.
- [ ] Release workflow produces an installable ZIP from a git tag.

## 4. Non-Goals for v0.1 (explicit)

- **No AI features.** The data model leaves room (JSON payloads, lead notes, events),
  but no AI code ships.
- **No SaaS/cloud sync.** No external services, no telemetry.
- **No payments/licensing.** No license keys, no update server.
- **No advanced builder:** no drag-and-drop reordering polish, conditional logic,
  multi-step forms, file uploads, or field types beyond text/email/textarea.
- **No email notifications** (yes, really — see Open Questions; likely the first 0.2 item).
- **No integrations** with any form plugin, CRM, or marketing tool.
- **No multisite-specific features** (must not break on multisite, but no network admin UI).
- **No import/export, no templates gallery, no analytics dashboards.**

Cutting notifications from 0.1 is the most debatable cut. Rationale: the product promise
is "leads live in the inbox," and notifications are additive, not structural. If review
says otherwise, it slots into Milestone 5 without touching architecture.

## 5. Technical Decisions (summary — full rationale in `docs/ARCHITECTURE.md`)

| Area | Decision |
|---|---|
| Repository | **Single-plugin repo**, not a multi-package monorepo. Monorepo-ready conventions so a Pro plugin can join later. |
| PHP | 8.1 minimum (host reality), typed, `Reinventx\` namespace, PSR-4 via Composer. |
| Runtime PHP deps | **None bundled.** Composer is for autoloading + dev tooling only. Avoids dependency conflicts between plugins. |
| WordPress | Min WP 6.6 (core ships the `react-jsx-runtime` handle wp-scripts targets); test against latest and latest−1. |
| Admin UI | React + TypeScript SPA on a single admin page, built with `@wordpress/scripts`. UI: **shadcn/ui + Tailwind, vendored, brand-black theme** (M1.5; isolation rules in ARCHITECTURE §4). `api-fetch`/`i18n` stay WP externals. |
| Public form | Server-rendered PHP templates + one small vanilla-TS script (~few KB). No framework. |
| Embedding | Shortcode + dynamic block, one shared render callback. |
| Data | Custom tables: `forms`, `leads`, `lead_notes`. Form field config and lead payloads as JSON columns. No CPT, no EAV. |
| API | WP REST API, namespace `reinventx-forms/v1`. Cookie+nonce auth for admin; unauthenticated public submission endpoint hardened separately. |
| Testing | PHPUnit (unit + wp-env integration), PHPStan (wordpress stubs), PHPCS (WordPress ruleset), ESLint/Prettier via wp-scripts. Playwright E2E deferred to post-0.1. |
| Local dev | `wp-env` (Docker). One command to a running site. |
| CI/CD | GitHub Actions: lint + static analysis + tests on PR; tag-triggered release builds the distributable ZIP. |

## 6. Open Questions (need founder input; defaults chosen so work isn't blocked)

1. **Naming:** ~~directory is `form-indox`~~ **Resolved:** slug `reinventx-forms`,
   text domain `reinventx-forms`, namespace `Reinventx Forms`, table prefix `rvtx_`.
2. **IP address storage:** GDPR-relevant. Default: store IP truncated/hashed, with a
   filter to disable entirely. Confirm target market's privacy posture.
3. **Email notifications in 0.1?** Currently cut (see Non-Goals). Cheap to add
   (`wp_mail` on a `rvtx_lead_created` action) if the promise feels broken without it.
4. **WordPress.org distribution vs. self-distributed?** Affects readme.txt, GPL
   compliance of all assets, and review-queue constraints. Default assumption: aim for
   .org compatibility from day one (it enforces good hygiene either way).
5. **Lead statuses: fixed set or user-configurable?** v0.1 default: fixed set stored as
   strings (not an enum column), so making them configurable later is a UI change,
   not a migration.

## 7. Milestones (detail + acceptance criteria in `docs/MILESTONES.md`)

- **M0 — Foundation:** repo, tooling, CI, activation, schema installer. *Nothing user-visible yet.*
- **M1 — Forms:** Forms domain + REST + admin shell; create and list forms.
- **M2 — Capture:** public rendering (shortcode), submission endpoint, validation, storage, context.
- **M3 — Inbox:** lead list, lead detail, status changes, notes.
- **M4 — Ship:** Gutenberg block, uninstall flow, hardening pass, release pipeline, **0.1.0 ZIP**.

Each milestone is independently testable and merges to `main` green.
