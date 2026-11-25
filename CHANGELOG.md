# Changelog

## 0.1.0 (Unreleased)

First release.

- Closes comments on every post type except an allowlist (`product` by default).
- Closes trackbacks and pingbacks everywhere, and starts new posts with trackbacks off.
- Refuses trackbacks before WordPress looks up the post.
- Removes the two pingback methods from XML-RPC and the `X-Pingback` header.
- Refuses logged-out writes to `/wp/v2/comments`. Logged-in requests, including block editor
  Notes, and new comments on allowlisted post types are left to core.
- Adds `wp comments-off status` and `wp comments-off close-existing [--yes]`.
- `GARRETTBUILDS_COMMENTS_OFF_DISABLE` turns the plugin off from `wp-config.php`.
