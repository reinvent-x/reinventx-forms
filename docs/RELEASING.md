# Releasing Reinventx Forms

## Cutting a release

1. **Bump the version** in all four places (then run `bin/check-version.sh vX.Y.Z`
   to prove they agree):
   - `reinventx-forms.php` — the `Version:` header
   - `reinventx-forms.php` — the `REINVENTX_VERSION` constant
   - `readme.txt` — `Stable tag:` (and add a changelog entry)
   - `package.json` — `version`
2. **Run the manual smoke test** below on a clean `wp-env` site.
3. Merge to `main`, wait for CI to go green.
4. Tag and push: `git tag vX.Y.Z && git push origin vX.Y.Z`.

`release.yml` then verifies the version sync, builds production assets
(`composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`),
assembles `dist/reinventx-forms.zip` honoring `.distignore`, proves the ZIP contains
no sources/tests/CI files, installs and activates it on a clean WordPress with
wp-cli (tables, schema option, and capabilities asserted), and attaches the ZIP
to a GitHub Release. A failure at any step means no release is published.

## Manual smoke-test checklist (the full vertical demo)

Install/activate/schema on a clean WordPress is verified automatically by the
release workflow against the actual ZIP; this checklist covers the human flows
it cannot click through. Run it on a clean site (`npm run env:start`, empty
DB). When QA'ing the packaged artifact itself, use the ZIP from the release
workflow run rather than the mounted source tree.

- [ ] Activate Reinventx Forms. The Reinventx Forms menu appears for the admin only.
- [ ] **Create a form** with all three field types; mark one required. Save,
      reopen, confirm it round-trips.
- [ ] **Embed it twice**: once as the Reinventx Form block (picker + preview
      must show the real form), once as `[rvtx_form id="…"]` on another page.
      Both render identically on the frontend.
- [ ] **Submit as a logged-out visitor** (block page and shortcode page):
      - invalid submission (missing required, bad email) shows inline errors,
        stores nothing;
      - valid submission shows the success message.
      - with JS disabled, the same two flows still work via page reload.
- [ ] **Inbox**: both leads appear newest-first with form name and source
      context (page URL + title). Filters and pagination behave.
- [ ] **Lead detail**: every field value, context block, submission time.
- [ ] **Status**: walk one lead through the pipeline (new → contacted); mark
      another as spam. Badges update in the inbox list; the status filter
      finds each.
- [ ] **Note**: add one; author and timestamp render.
- [ ] **Archive the form**: public pages show nothing to visitors (a
      placeholder for logged-in editors); the inbox still shows its leads.
- [ ] **Uninstall drill**: with "Delete all data on uninstall" OFF, delete the
      plugin (from a copy — see the bind-mount warning in README), reinstall,
      confirm data survived. Repeat with the toggle ON: tables and options gone.
