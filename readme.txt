=== Reinventx Forms ===
Contributors: meladsamuel
Tags: form, contact form, leads, lead management, crm
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone form and lead management — create forms, capture leads with source context, and track follow-up status.

== Description ==

Reinventx Forms is a standalone form and lead management plugin. It does not depend on any other form plugin: Reinventx Forms owns the whole flow, from building the form to tracking the follow-up.

* **Build forms** with text, email, and paragraph fields in a fast React admin.
* **Embed anywhere** with the Reinventx Form block or the `[rvtx_form id="…"]` shortcode.
* **Lightweight by design**: the public page loads zero React — a server-rendered form plus a ~2 KB enhancement script, and the form still works with JavaScript disabled.
* **Every lead lands in your inbox** with its source context: the page URL and title it came from, the referrer, and the submission time.
* **Track the follow-up**: statuses (new, contacted, qualified, won, lost, spam) and internal notes with author attribution.
* **Spam resistance built in**: honeypot, signed time-trap token (cache-safe), and rate limiting — no third-party service.
* **Privacy-aware**: visitor IP addresses are never stored, only a keyed hash — and leads work with WordPress's built-in personal-data export and erasure tools. See the Privacy section for exactly what a lead can contain.

Email notifications are not included yet — leads are collected in the Reinventx Forms inbox inside wp-admin (notifications are the first item on the 0.2 roadmap).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/reinventx-forms` directory, or install the plugin through the WordPress plugins screen.
2. Activate Reinventx Forms through the Plugins screen in WordPress.
3. Go to **Reinventx Forms** in the WordPress admin menu.
4. Create a form, then embed it with the **Reinventx Form** block or the `[rvtx_form id="…"]` shortcode.
5. View submitted leads in the Reinventx Forms inbox.

== Frequently Asked Questions ==

= Does it work with my page builder? =

Yes — anywhere shortcodes work, `[rvtx_form id="…"]` works. Block themes and the block editor get a native Reinventx Form block.

= What happens to my data if I delete the plugin? =

Nothing, by default. Forms, leads, and notes survive uninstall unless you explicitly enable "Delete all data on uninstall" in Reinventx Forms → Settings.

= Does it send email notifications? =

Not yet — that is the first item on the 0.2 roadmap. Leads live in the Reinventx Forms inbox in wp-admin.

== Development ==

The development source, build tools, and tests are maintained publicly at:
https://github.com/reinvent-x/reinventx-forms

The WordPress.org release ZIP contains the built plugin assets. To build from source, see the repository README.

== Privacy ==

Reinventx Forms stores form submissions as leads in your site's own database. Nothing is sent to any third-party service, and the plugin makes no external network requests.

Depending on the form and how it is embedded, a stored lead may include:

* The values the visitor entered into the form.
* The URL and page title the form was submitted from.
* The HTTP referrer sent by the visitor's browser.
* The visitor's browser user-agent string.
* A keyed hash derived from the visitor's IP address.

Raw IP addresses are never stored. The stored value is an HMAC hash, and it can be switched off entirely with the `rvtx_store_ip_hash` filter — per-client rate limiting keeps working either way, because it uses a separate short-lived hash that is never written to the database.

Referrer and source URLs are stored as the browser reports them. If pages on your site carry sensitive information in query strings, consider filtering or shortening what is recorded.

Leads are integrated with WordPress's built-in privacy tools. Under Tools → Export Personal Data, a request for an email address returns every lead containing that address — its form, submitted values, source context, and any internal notes staff have attached to it. Under Tools → Erase Personal Data, those same leads and their notes are deleted outright rather than anonymised in place. Matching ignores letter case and surrounding whitespace.

Site owners are responsible for describing this collection in their own privacy policy and for setting an appropriate retention practice.

== Screenshots ==

1. Forms list.
2. Form editor with text, email, and paragraph fields.
3. Lead inbox with status filters.
4. Lead detail view with submitted fields, source context, status, and notes.
5. Settings screen with the uninstall data-deletion option.

== Changelog ==

= 0.1.2 =
* Fixed: personal-data export now includes the internal notes attached to a lead. Erasure already deleted them, so the export was disclosing less than the eraser destroyed.
* Fixed: an email address requested with surrounding whitespace now matches, and an empty address no longer reaches the matcher.

= 0.1.1 =
* Added: leads are now covered by WordPress's personal-data tools — Tools → Export Personal Data returns a person's leads, and Tools → Erase Personal Data deletes them along with any internal notes.
* Fixed: returning false from `rvtx_store_ip_hash` no longer disables per-client rate limiting. Rate limiting now uses its own short-lived hash that is never stored, so opting out of IP storage is a privacy choice rather than a reduction in abuse protection.
* Added: a Privacy section documenting exactly what a stored lead can contain.

= 0.1.0 =
* Initial release: form builder (text/email/paragraph fields), block + shortcode embedding, server-rendered public form with progressive enhancement, spam guards (honeypot, time-trap token, rate limit), lead inbox with source context, statuses (new, contacted, qualified, won, lost, spam), and notes.
* Privacy and tuning filters: `rvtx_store_ip_hash` (return false to store nothing IP-derived; also disables per-client rate limiting, which keys on the hash) and `rvtx_rate_limit_max` (submissions-per-minute threshold).

== Upgrade Notice ==

= 0.1.2 =
Personal-data export now includes internal notes, matching what erasure already deleted.

= 0.1.1 =
Adds WordPress personal-data export and erasure for leads, and fixes rate limiting being disabled when IP-hash storage is turned off.

= 0.1.0 =
Initial release.
