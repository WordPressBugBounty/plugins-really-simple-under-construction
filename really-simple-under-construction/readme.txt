=== Really Simple Under Construction Page – Coming Soon & Maintenance Mode ===
Tags: under construction, coming soon, maintenance mode, maintenance, hide site
Requires at least: 6.0.0
Tested up to: 7.1
Stable tag: 1.6.4
License: GPLv2 or later
Contributors: jonashjalmarsson
Requires PHP: 7.4
Donate link: https://paypal.me/byjalma

Put your site behind an Under Construction page. Coming soon and maintenance mode, with a secret-word URL or IP whitelist so you can keep working.

== Description ==

Tick one checkbox and your site is hidden behind an Under Construction page. Visitors see your holding page; you and anyone you let through see the real site. That is the whole plugin.

Two ways to get past the page without logging in:

* **A secret word.** Visit `yoursite.com/?yoursecret` once and a cookie keeps that browser through. Good for showing a client work in progress.
* **An IP whitelist.** Your office, your phone, an uptime monitor — they always see the live site.

Logged-in users are never blocked, and `/wp-admin`, the REST API, WP-CLI and cron keep working normally.

The page itself is your HTML. Paste in a full document with DOCTYPE, `<style>` and `<body>` if you want, or a single line of text. No page builder, no template picker, no upsell wizard — no settings you have to learn before the plugin does its job.

= Use it for =

* Coming soon pages for a site that has not launched yet.
* Maintenance mode while you rebuild, migrate or debug.
* Staging and client-preview sites you want off the public web and out of Google.

= Settings =

Go to the settings page in Settings > Really Simple Under Construction. Enable by checking the checkbox. The Under Construction page is only visible to visitors who are not logged in. You can optionally configure:

1. The Under Construction page itself — paste any HTML (DOCTYPE, &lt;style&gt;, &lt;body&gt; etc. are supported, scripts are stripped).
2. A secret word that lets you bypass the page via a URL like `?yoursecret`. A cookie is then stored so the same browser keeps access.
3. The lifetime of that cookie, in days.
4. A list of IP addresses to whitelist for users and services that should always see the real site.

== Changelog ==

= 1.6.4 =
* Fixed: the under-construction page could be bypassed by adding `?x=/wp-json/` or `?wc-api=1` to an address. Only real REST requests and WooCommerce callbacks are let through now.
* Fixed: after changing the secret word, a visitor with the old cookie could not get back in with the new word in the address. The new word now works; the old cookie still does not.
* Fixed: a secret word containing a dot did not work.
* The plugin's settings are removed when it is deleted.

= 1.6.3 =
* A small, dismissible review request on this plugin's own settings page (never a site-wide notice). It appears once the under-construction page has been switched on and the settings page has been in use for 7 days, only to administrators. "Maybe later" hides it for 30 days, "Don't ask again" for good. No external requests are made — it is a plain link to the plugin's review page on wordpress.org.

= 1.6.2 =
* The secret-word bypass link no longer stays in your address bar, browser history or Referer: right after the bypass cookie is set you are redirected to the same page without the secret word.
* The "Get Pro" links now carry a small `ref` marker for where they were clicked (settings page vs. Plugins screen), so the project page can tell which one is used. Nothing is sent from your site; it is part of the link only.

= 1.6.1 =
* The Pro upgrade box now offers a direct download of the Pro add-on's .zip alongside the existing link to its plugin page, so you can install Pro and buy the license from inside it if you prefer. Both are plain links — this plugin still installs, activates and downloads nothing on your behalf, and contacts no external server of its own.

= 1.6.0 =
* Removed the in-plugin LemonSqueezy purchase, license-activation and auto-install flow entirely. The free plugin no longer contacts any external service. Pro purchases now happen on the project landing page (https://jonashjalmarsson.se/plugins/really-simple-under-construction-pro/) and the Pro plugin itself handles license activation in its own License tab. The "Get Pro" link in the Plugins screen and the upsell card above the settings page now simply open the landing page in a new tab.

= 1.5.8 =
* Re-style the Pro upsell card on the settings page so it no longer uses WordPress core notice classes (Guideline 11 hygiene). Same scope as before — only the plugin's own settings page — but it now renders as a self-styled card with a small "Pro upgrade" label, distinct from a system notice. Drops the inline color/weight from the "Get Pro" link in plugin_action_links too.
* Pro upsell card is now collapsible — click the × in the corner to shrink it down to just the "Pro upgrade" pill; click the pill to expand it back. State persists per browser via localStorage.

= 1.5.7 =
* Fix: when buying PRO from inside the free plugin, the license is now activated against LemonSqueezy *during* the install flow rather than left in a "pending" state for the user to activate manually afterwards. Errors that previously surfaced two steps later (most commonly "license has reached its activation limit") now show up in the install notice immediately, so the user can act on them right away. PRO's License tab opens already showing an active status when install succeeds.

= 1.5.6 =
* Hotfix: the "Get PRO" checkout link was missing `/checkout/` in the URL path and 404'd. Buy flow now lands on the correct LemonSqueezy checkout. No other changes.

= 1.5.5 =
* Internal refactor: the PRO upsell + auto-install flow is now a reusable module. No user-visible change.
* Added a "Read more →" link next to the Get PRO button so users can read about PRO on jonashjalmarsson.se before clicking through to checkout.

= 1.5.4 =
* New: a "Get Pro" link in the Plugins list and a small notice at the top of the settings page when the PRO add-on isn't installed. Both are hidden automatically once PRO is active.
* New: clicking "Get PRO" from the settings page now opens the LemonSqueezy checkout in an overlay (lemon.js) instead of a new tab. After purchase, the same notice swaps into a short installer flow that fetches your license, downloads PRO from our update server, installs and activates it, and pre-fills the license key in PRO's License tab — without leaving wp-admin. A paste-the-key fallback is shown if anything in the auto flow stalls.

= 1.5.3 =
* Developer: added `rsuc_html_output` filter so add-ons can post-process the Under Construction HTML before it goes to the wire (e.g. resolve shortcodes inside the template). No visible changes for end users.
* Bugfix: `/wp-admin/*` paths beyond the bare `/wp-admin/` root were being intercepted by the UC page because the admin-URL match compared a no-scheme host+path against a full URL. Switched to a `REQUEST_URI` prefix match so `admin-post.php`, `admin-ajax.php`, etc. reach WordPress as intended.

= 1.5.2 =
* Developer: added two filter hooks for add-on plugins to extend the bypass logic. `rsuc_ip_whitelisted` (passes the resolved client IP) lets an add-on whitelist by CIDR range, geolocation, or any other rule. `rsuc_should_bypass` runs right before the UC page would render, so an add-on can match multiple secret URLs, time windows, or other custom signals. No visible changes for end users.

= 1.5.1 =
* Developer: added action `rsuc_render_after_heading` on the settings page so add-on plugins can render a tab nav, an upgrade banner, or other UI between the heading and the settings form. No visible changes for end users.

= 1.5.0 =
**Recommended security update for all existing users.** No settings changes, no migration — install the update, refresh the settings page, and you are done. Headlines below.

* **Security:** the Under Construction HTML field now strips `<script>` tags, on-event handlers (onclick/onload/etc.) and `javascript:` URIs while preserving structural tags (DOCTYPE, html, head, style, body). A compromised admin account can no longer use this field to inject JavaScript that runs for visitors and other admins.
* **Security:** the bypass cookie now sets Secure (when the site uses HTTPS), HttpOnly, and SameSite=Lax attributes — was readable from JS and replayable over plain HTTP before.
* **Security:** the cookie value is now compared with `hash_equals()` to avoid timing leaks.
* **Security:** the IP whitelist now validates entries with `FILTER_VALIDATE_IP` (rejects bogus addresses like `999.x.x.x` and accepts both IPv4 and IPv6).
* Bugfix: bail early on WP-CLI and cron requests — previously the plugin tried to render the Under Construction page during `wp` commands and `wp-cron.php` calls, which killed those commands silently. Cron jobs and CLI scripts now run normally.
* Bugfix: REST API requests (any URL containing `/wp-json/`) are now correctly bypassed. The 1.4.6 check used the wrong server variable, so REST calls were getting the Under Construction page when the plugin was active — silently broken since the bypass was added. Public REST endpoints work again.
* Bugfix: the "skip plugin if request is to /wp-json/" guard read `$GLOBALS['PHP_SELF']` which is never populated, so the bypass never fired. Switched to `$_SERVER['REQUEST_URI']` for actual reliability.
* Behavior: the Under Construction page now responds with HTTP 503 + `Retry-After`, so search engines see "temporarily unavailable" (correct semantics, won't deindex) and proxies/CDNs no longer cache the placeholder over a real launch. Visitors see the same page as before.
* Hygiene: every translatable string now uses the matching `really-simple-under-construction` text domain (was `rsuc` short form), textareas are escaped with `esc_textarea`, every `register_setting` call has a `sanitize_callback`, the "Add my IP" button uses `addEventListener` + a JSON-encoded value. Bundled language files renamed accordingly.
* Tested up to WordPress 6.9.4.

= 1.4.6 =
* Bugfix, not working for startpage since 1.4.5.

= 1.4.5 =
* Minor code cleanup

= 1.4.4 =
* Improved handling of login page

= 1.4.3 =
* Added setting to make Wordpress static Homepage to be visible, the plugin still restricts all other pages.

= 1.4.2 =
* Ignore if call to webhook wp-json

= 1.4.1 =
* Bugfix Wordpress login blocked

= 1.4 =
* Ignore if call to webhook wc-api

= 1.3.2 =
* Minor bugfixes

= 1.3.1 =
* Add your IP to textfield link added.

= 1.3 =
* Whitelisting with IP address added. Settings layout updated. Refactored code.

= 1.2.1 =
* Settings link added in plugins list. Author information updated.

= 1.2 =
* Bugfix, not working for startpage in some set ups.

= 1.0 =
* Language support added. sv_SE and en_US in first version.

= 0.2 =
* Fix to ignore "Under Construction" page if current page is wp-admin or wp-login.php. Handles custom URLs.

= 0.1 =
* First commit.

== Upgrade Notice ==

= 1.6.4 =
Closes ways to bypass the under-construction page and fixes a secret word with a dot and re-entry after changing the word.

= 1.6.3 =
Adds a small dismissible review request on the plugin's own settings page after 7 days of use. No external requests.

= 1.6.2 =
The secret-word bypass link is cleaned from the address bar after use.

= 1.6.1 =
The Pro upgrade box now includes a direct download link for the Pro add-on, next to the existing link to its plugin page. Plain links only — nothing is installed or downloaded for you, and no external server is contacted.

= 1.6.0 =
Free plugin now focuses on the under-construction page only. Pro purchase and license flow moved to the Pro landing page and the Pro plugin's own License tab. Existing Pro installs keep working unchanged.

= 1.5.0 =
Recommended security update for all users. Blocks JavaScript injection on the Under Construction page, hardens the bypass cookie (Secure/HttpOnly/SameSite), tightens IP-whitelist validation, and fixes silent breakage with REST API, WP-CLI and cron.

= 0.2 =
Upgrade, fixing beeing locked out from wp-admin if no secret word is set.

= 0.1 =
First commit.

== Screenshots ==

1. The settings page in Settings > Really Simple Under Construction. Toggle the page on, paste in your HTML, set a secret word, and add IP addresses to the whitelist.
2. An Under Construction page on the public frontend, rendered from the HTML saved in the settings.
