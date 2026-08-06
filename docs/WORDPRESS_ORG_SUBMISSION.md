# WordPress.org Submission Checklist

> GitHub is the development repository. WordPress.org SVN is the release
> repository after approval.

## Rename (2026-07-25)

The first submission used the display name "FormInbox" / slug `forminbox`.
The plugins team pended it: the name was flagged as colliding with an existing
form-management product, and `form` is too common a word to serve as a prefix.
Everything was renamed to **Reinventx Forms** / slug `reinventx-forms`:

| Thing | Now |
|---|---|
| Display name | Reinventx Forms |
| Slug / text domain / main file | `reinventx-forms`, `reinventx-forms.php` |
| PHP namespace | `Reinventx\…` (PSR-4 → `src/`) |
| Constants | `REINVENTX_VERSION`, `REINVENTX_FILE` |
| Tables, options, hooks, caps, transients, REST error codes | `rvtx_` prefix |
| Capabilities | `rvtx_manage_forms`, `rvtx_manage_leads` |
| Tables | `{$wpdb->prefix}rvtx_forms`, `…rvtx_leads`, `…rvtx_lead_notes` |
| Options | `rvtx_schema_version`, `rvtx_delete_data_on_uninstall` |
| Filters/actions | `rvtx_lead_created`, `rvtx_lead_status_changed`, `rvtx_store_ip_hash`, `rvtx_rate_limit_max` |
| Shortcode | `[rvtx_form id="…"]` |
| Block | `reinventx-forms/form`, title "Reinventx Form" |
| REST namespace | `reinventx-forms/v1` |
| Script/style handles, DOM ids, CSS classes, data attrs | `rvtx-…` / `data-rvtx-…` |
| Admin menu slug | `reinventx-forms` |

The reply to the plugins team must **explicitly request the new slug**
`reinventx-forms` — changing it in the code is not enough.

## Before submitting

- [ ] **Confirm slug and name.** Requested slug: `reinventx-forms`, display name
      "Reinventx Forms". The slug is assigned permanently at review time — verify it is
      still available and matches the plugin's text domain (`reinventx-forms`) and
      main file (`reinventx-forms.php`).
- [ ] **Contributors** in `readme.txt` is the wp.org username (`meladsamuel`).
- [ ] **`composer.json` ships in the ZIP** (it is not in `.distignore`).
- [ ] **`assets/` never ships in the ZIP** — directory banners/icons/screenshots
      go to SVN `assets/` only. `.distignore` excludes it and `bin/build-zip.sh`
      fails the build if it leaks.
- [ ] **Validate `readme.txt`** with the official validator
      (https://wordpress.org/plugins/developers/readme-validator/): headers,
      `Stable tag`, `Tested up to` current, changelog present, no markdown-only
      syntax that the wp.org parser mangles.
- [ ] **Quality gates** (all must pass — see README for the Docker equivalents
      if the host has no PHP/Node):
  - [ ] `composer check` (PHPCS, PHPStan level 8, unit tests)
  - [ ] `pnpm run typecheck`
  - [ ] `pnpm run lint:js`
  - [ ] `pnpm run build`
  - [ ] `pnpm run test:php` (where wp-env is available)
- [ ] **Create the release ZIP from a tag** (`git tag vX.Y.Z && git push origin vX.Y.Z`)
      and let `release.yml` produce it — it enforces version sync, `.distignore`
      leak checks, and a clean-install smoke test. Do not hand-roll the ZIP.
- [ ] **Test the ZIP on a clean WordPress install** (the workflow does
      install/activate/schema automatically; also run the human checklist in
      `docs/RELEASING.md` — block editor flow, submissions with and without JS,
      inbox, uninstall toggle in both positions).

### Source availability note

- The repository at https://github.com/reinvent-x/reinventx-forms is public and acts
  as the canonical development/source repository.
- The release ZIP intentionally excludes source and build tooling (`client/`
  TypeScript sources, tests, lockfiles, package files) per `.distignore`;
  it ships the built assets and the production autoloader.
- WordPress.org reviewers can access the full source, build tools, and test
  suites at the GitHub repository above.
- `Plugin URI` points to https://forms.reinventx.com/. The plugins team
  requires it to carry the owning entity's domain before the
  `reinventx-forms` slug can be granted (see "Slug ownership" below).
  A subdomain satisfies this; the ownership proof is still a TXT record at
  the registrable root, not at the subdomain.

## Slug ownership (2026-07-27)

The plugins team held the `reinventx-forms` slug pending proof that the
submitter controls the entity domain the name implies. A plugin named after
an entity must demonstrate it is not trading on someone else's brand.

Two things are required together:

1. `Plugin URI` carries the entity domain (not a GitHub URL).
2. Ownership of `reinventx.com` is verified.

Verification is by TXT record at the domain root:

    @  TXT  wordpressorg-meladsamuel-verification

Alternatives the team accepts: a WordPress.org profile email under the
domain, a new account created with such an address, or established plugins
already in the directory under the same account. Renaming the plugin so it
implies no entity affiliation is the fallback if ownership cannot be shown.

**The domain must also serve the page `Plugin URI` points at.** Reviewers
follow it. At the time of writing `reinventx.com` resolves in DNS and handles
mail but returns nothing over HTTP, and `forms.reinventx.com` has no DNS
record at all — both need to exist before the slug request is sent.

## Reviewer note: public form submissions

Reinventx Forms exposes one intentionally public REST endpoint,
`POST reinventx-forms/v1/submissions`, used by visitor form submissions. Visitors
are anonymous and public pages are often served from page caches, so a
logged-in WordPress nonce is the wrong tool: it would be cached stale and
fail legitimate submissions. Instead, every rendered form embeds a
form-bound, HMAC-signed timestamp token, and the endpoint applies
server-side protections:

- honeypot field (submissions that fill it are rejected),
- minimum-fill-time validation via the signed timestamp (too-fast = bot),
- strict content-type checks (JSON only on the REST path),
- per-field server-side sanitization and validation through the field type
  registry; input keys not defined by the form are discarded,
- per-client rate limiting,
- escaped output everywhere submission data is displayed — stored values are
  treated as untrusted forever.

Every other REST route has an explicit capability-based `permission_callback`.

## Plugin directory assets (not part of the ZIP)

These live in the SVN `assets/` directory, not in the plugin. Prepare before
or right after approval; the Screenshots section in `readme.txt` (numbered
captions) must match the `screenshot-N.png` files.

| File | Purpose |
|---|---|
| `assets/icon-128x128.png` | Directory icon (small) |
| `assets/icon-256x256.png` | Directory icon (retina) |
| `assets/banner-772x250.png` | Plugin page banner (a 1544x500 retina variant is optional) |
| `assets/screenshot-1.png` | Forms list |
| `assets/screenshot-2.png` | Form editor |
| `assets/screenshot-3.png` | Lead inbox with status filters |
| `assets/screenshot-4.png` | Lead detail |
| `assets/screenshot-5.png` | Settings screen |

No placeholder images are committed to this repo; produce final PNGs from a
seeded site (`bin/seed.php`) at the exact dimensions above.

## Submission and after approval

- [ ] Submit the tagged release ZIP through
      https://wordpress.org/plugins/developers/add/ and respond to reviewer
      feedback from the plugins team (initial review commonly takes days to weeks).
- [ ] After approval, publish through **WordPress.org SVN**: check out the
      assigned SVN repo, copy the ZIP contents into `trunk/`, upload directory
      assets to `assets/`, tag the release under `tags/X.Y.Z/`, and confirm
      `Stable tag` in `trunk/readme.txt` points at it.
- [ ] Add the SVN deploy step to `release.yml` afterwards (ARCHITECTURE §9
      anticipates this) so GitHub tags and SVN releases cannot drift.

## Review-readiness notes (what reviewers look for)

- All output escaped at render time; direct DB queries only through the
  repository classes with `$wpdb->prepare()` — both enforced by PHPCS/PHPStan
  in CI.
- Public REST endpoint is intentionally unauthenticated (submissions) and
  hardened server-side; every other route has an explicit capability check.
- No external services, no tracking, no bundled PHP runtime dependencies
  (Composer autoloader only). Raw visitor IPs are never stored.
- Uninstall is destructive only with the explicit opt-in setting.
