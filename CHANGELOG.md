# Changelog

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
