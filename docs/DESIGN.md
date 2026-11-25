# Design Notes

Why the plugin is built the way it is. Read this before changing a hook. Core behavior below was
read from WordPress 7.1.5 source.

## Principles

- **One file, core hooks only.** It has to run unchanged on any host, including shared hosting
  with no shell and managed hosts that block PHP from writing files.
- **No database writes during web requests.** Every web-request change is a read-time filter.
  Removing the file restores the site's stored settings. Only the WP-CLI command
  `close-existing --yes` writes, and only when someone runs it.
- **Narrow, not broad.** Only comment and ping paths are touched. No blanket REST or XML-RPC
  blocking, because form plugins, Jetpack, MCP servers and automation tools use those.
- **Say why, don't just hide.** Controls that can't work are removed; screens that still matter
  get a notice explaining where comments are controlled.
- **Priority 99** on filters, so a theme or plugin filtering at the default priority can't reopen
  comments.

## Each Path Spam Uses, and What Stops It

| Path | Core's own check | What the plugin adds |
|---|---|---|
| `wp-comments-post.php` | `wp_handle_comment_submission()` refuses with 403 when `comments_open()` is false, before `pre_comment_on_post` fires | `comments_open` filter. Backstop on `pre_comment_on_post` in case something reopens comments after us |
| `wp-trackback.php` (and `/trackback/`) | Fires `pre_trackback_post`, then refuses when `pings_open()` is false | `pings_open` returns false everywhere. Early exit on `pre_trackback_post` with the standard trackback error, sent as XML |
| XML-RPC `pingback.ping` | Refuses when `pings_open()` is false | Method removed, with `pingback.extensions.getPingbacks`. XML-RPC itself stays on |
| XML-RPC `wp.newComment` | Refuses when `comments_open()` is false; anonymous only if a filter allows it | Nothing extra; the `comments_open` filter covers it |
| REST `POST /wp/v2/comments` | Refuses anonymous comments unless `rest_allow_anonymous_comments` is on; refuses non-Notes when comments are closed | Refuses logged-out writes on `/wp/v2/comments` in `rest_pre_dispatch`, except new comments on allowlisted post types. Passes through any earlier result or error |
| `X-Pingback` header | Sent on every page | Removed |
| New posts | Take `default_ping_status` | `pre_option_default_ping_status` returns `closed` (read-time only) |

`default_comment_status` is left alone on purpose. Forcing it closed could create new WooCommerce
products with reviews closed. The `comments_open` filter already covers every other post type.

## Block Editor Notes

Notes (WordPress 6.9+) are stored as comments with type `note`. They keep working because:

- They come from logged-in editors, and the REST rule only acts on logged-out requests.
- The REST comments controller skips the `comments_open()` check for Notes.
- Whether a post type has Notes comes from `check_post_type_supports_notes()`, which reads the
  `editor` support's `notes` flag, not `comments` support. Removing comment support in the admin
  doesn't affect them.

## Admin Changes

- **Admin bar bubble:** removed only when no allowlisted post type is registered. On WooCommerce
  sites the bubble stays, since it is where pending reviews show.
- **Discussion controls:** `comments` and `trackbacks` support is removed from closed post types
  on `wp_loaded`, in admin requests only. That hides the classic Discussion box, the Comments
  column and the Quick Edit and Bulk Edit controls.
  - `admin-ajax.php` counts as admin even when the public site calls it (load more, filters), so
    AJAX is skipped, except Quick Edit's save (`inline-save`), which redraws a list row and must
    match the list's columns.
  - Post types registered after `wp_loaded` keep their controls. That is rare and was judged not
    worth the extra code.
- **Block editor Discussion panel:** the block editor reads post type support from
  `/wp/v2/types`. `rest_prepare_post_type` drops `comments` and `trackbacks` from `supports` for
  closed post types. Only that route is filtered.
- **Notice:** administrators see it on Settings, Discussion and the Comments screen. On multisite,
  only network administrators see the file and `wp-config.php` steps. The file path is shown
  relative to the WordPress root, so custom content directories show the right path.

## Considered and Left Out

| Idea | Why not |
|---|---|
| Turn XML-RPC off entirely | Breaks Jetpack and the WordPress apps |
| Hide the Comments menu and Discussion settings | Product reviews, avatars and moderation use them, and hiding the Comments screen hides evidence if spam gets through |
| Force `default_comment_status` closed | New WooCommerce products could start with reviews closed |
| Rewrite post data on save (`wp_insert_post_data`) | Would write to the database during web requests |
| Server rules (Nginx, `.htaccess`) for `wp-comments-post.php` | Differ by host; the PHP checks are enough |
| Remove comment support on the front end | Some themes check it before showing existing comments, which would hide them |

## Maintenance

The plugin leans on core's own checks, so a WordPress change can quietly open a path. On each
major WordPress release, before updating sites:

1. Read `wp-includes/comment.php` (`wp_handle_comment_submission`), `wp-trackback.php`, the
   pingback and `wp.newComment` methods in `class-wp-xmlrpc-server.php`, and
   `class-wp-rest-comments-controller.php` (`create_item_permissions_check`,
   `check_post_type_supports_notes`). Confirm the checks in the table above are still where this
   file expects them.
2. Check for new comment routes or comment types (Notes was new in 6.9).
3. Run the README's curl tests and admin checks on a staging site.
4. Update "Requires at least" and the WordPress badge if the minimum changes.

Automated: CI runs on every push and monthly on PHP 7.4 to 8.5, so a new PHP release or a retired
GitHub action shows up without a code change. Dependabot opens a pull request when an action used
in CI has a new version. When a new PHP version ships, add it to the matrix in
`.github/workflows/php.yml`; when the oldest one is no longer used, raise "Requires PHP".

## Review History

- Plan and code were reviewed against WordPress core source before release. Two outside reviews
  (plan, then code) found one real bug in 0.1.0 before release: `close-existing` couldn't save
  `default_ping_status` while the plugin's own read-time override was active, because
  `update_option()` compares against the filtered value. Fixed by removing the override around the
  read and the write.
- The 0.2.0 admin changes were reviewed the same way. Fixes from that review: the admin bar bubble
  stays when product reviews exist, front-end AJAX is left alone, the notice path works with custom
  content directories, and multisite site administrators get the right instructions.
- Tested on a live WP Engine site (WordPress 7.1.5, PHP 8.4) with the curl checks in the README.
