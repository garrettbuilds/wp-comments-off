# Changelog

## 0.2.1 (2026-11-25)

- Admin notice names only allowlisted post types the site has, by their label (for example
  "Products"). Sites without WooCommerce no longer see "product" listed.

## 0.2.0 (2026-11-25)

Admin changes, so nobody turns comments on and wonders why nothing happens. The front end is
unchanged.

- Admin bar: removes the comments bubble, except when an allowlisted post type is registered
  (WooCommerce products), where it shows pending reviews.
- Editors: hides the Discussion panel in the block and classic editors, and the Comments column
  and Quick Edit and Bulk Edit comment controls, on post types where comments are closed.
  Front-end requests, including `admin-ajax.php` calls from the public site, keep normal post
  type support, so themes still show existing comments. Block editor Notes are unaffected.
- Notice: Settings, Discussion and the Comments screen tell administrators comments are off
  site-wide (network-wide on multisite), which post types still follow the settings, and how to
  turn comments back on. On multisite, only network administrators see the file and
  `wp-config.php` steps.
- Docs: `docs/DESIGN.md` explains each hook, the core checks it relies on, and what was left out.
  README has "What Changes in the Admin" and "Turning Comments Back On".
- Tests: smoke test grows from 17 to 32 checks. CI adds PHP 8.5 and a monthly run; Dependabot
  keeps the CI actions current. `docs/DESIGN.md` has a checklist for each WordPress release.

## 0.1.2 (2026-11-25)

- Author URI in the plugin header is a plain https://www. link, without tracking parameters. No change in behavior.

## 0.1.1 (2026-11-25)

- Author link uses https://www. with tracking parameters. No change in behavior.

## 0.1.0 (2026-11-25)

First release.

- Closes comments on every post type except an allowlist (`product` by default).
- Closes trackbacks and pingbacks everywhere, and starts new posts with trackbacks off.
- Refuses trackbacks before WordPress looks up the post.
- Removes the two pingback methods from XML-RPC and the `X-Pingback` header.
- Refuses logged-out writes to `/wp/v2/comments`. Logged-in requests, including block editor
  Notes, and new comments on allowlisted post types are left to core.
- Adds `wp comments-off status` and `wp comments-off close-existing [--yes]`.
- `GARRETTBUILDS_COMMENTS_OFF_DISABLE` turns the plugin off from `wp-config.php`.
