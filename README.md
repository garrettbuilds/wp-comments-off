# Comments Off

A one-file WordPress must-use plugin that closes comments, trackbacks and pingbacks on every
post type, and keeps them closed. WooCommerce product reviews keep working.

Turning comments off under Settings, Discussion only changes new posts. Every post, page and
media file published before that still accepts comments or trackbacks, and spam bots find them.
This plugin closes them all while it runs, without changing anything in the database.

## What It Closes

| Where spam comes in | What the plugin does |
|---|---|
| The comment form (`wp-comments-post.php`) | Refused with a 403 on every post type except the allowlist |
| Trackbacks (`wp-trackback.php`) | Refused before WordPress looks up the post |
| Pingbacks over XML-RPC | `pingback.ping` and `pingback.extensions.getPingbacks` removed |
| The `X-Pingback` header | Removed, so the site stops advertising a pingback endpoint |
| Comment writes to `/wp-json/wp/v2/comments` from logged-out visitors | Refused with a 401, except new comments on allowlisted post types, which core checks as usual |
| New posts | Start with trackbacks off (read-time override, nothing saved) |

## What It Leaves Alone

- **Every other REST route.** It only looks at writes to `/wp/v2/comments`. Plugin APIs, form
  plugins, MCP servers, Zapier and Google Tag Manager are unaffected.
- **XML-RPC.** Only the two pingback methods are removed. Jetpack and the WordPress mobile apps
  keep working.
- **Block editor Notes.** Notes are stored as comments, but they come from logged-in editors and
  WordPress doesn't check `comments_open()` for them.
- **WooCommerce product reviews.** The `product` post type keeps its own comment setting.
- **Existing comments.** Nothing is deleted or hidden. The Comments screen still shows everything.
- **The admin.** No menus are hidden and no settings screen is added.

## Requirements

- WordPress 6.9 or later
- PHP 7.4 or later
- Any host. It uses only WordPress core hooks and has no host-specific code, so it is written to
  run the same on GoDaddy (shared and Managed WordPress), WP Engine, Kinsta and similar hosts.
  Hosts with their own comment lockdown setting, such as GridPane, don't need it.

## Install

Copy `garrettbuilds-comments-off.php` into `wp-content/mu-plugins/`. Create the `mu-plugins`
folder if it isn't there; GoDaddy shared hosting often doesn't have one. There's nothing to
activate. The plugin runs as soon as the file is in place.

By SFTP or SSH:

```sh
mkdir -p wp-content/mu-plugins
curl -fsSL -o wp-content/mu-plugins/garrettbuilds-comments-off.php \
  https://raw.githubusercontent.com/garrettbuilds/wp-comments-off/main/garrettbuilds-comments-off.php
```

Without shell access, upload the file with the host's file manager.

Check it loaded: **Plugins, Must-Use** lists "Comments Off". With WP-CLI:

```sh
wp plugin list --status=must-use
wp comments-off status
```

## Commands

The plugin adds two WP-CLI commands. Neither runs unless someone types it.

```sh
# What the plugin is doing, and how many posts are still stored as open
wp comments-off status

# Report what a permanent close would change
wp comments-off close-existing

# Make the change: close stored comments (except allowlisted types) and pings, and set
# "Allow link notifications" to off. Back up the database first.
wp comments-off close-existing --yes
```

`close-existing --yes` is the only thing that writes to the database. Run it if you want
comments to stay closed after the plugin is removed, then purge the host's page cache so cached
pages stop showing comment forms.

While the plugin runs, Settings, Discussion shows "Allow link notifications" unchecked whatever
is stored, and saving that screen can't change it. `wp comments-off status` shows the stored
value.

Without WP-CLI (common on GoDaddy shared hosting), use Bulk Edit on the Posts and Pages screens
(Comments: Do not allow, Pings: Do not allow). Media files and custom post types aren't on those
screens, so run this in phpMyAdmin instead, after a backup. Change `wp_` if the site uses another
table prefix, and add any other allowlisted post types to the `NOT IN` list.

```sql
UPDATE wp_posts SET comment_status = 'closed'
 WHERE comment_status = 'open' AND post_type NOT IN ('product')
   AND post_status NOT IN ('auto-draft', 'trash');
UPDATE wp_posts SET ping_status = 'closed'
 WHERE ping_status = 'open' AND post_status NOT IN ('auto-draft', 'trash');
UPDATE wp_options SET option_value = 'closed' WHERE option_name = 'default_ping_status';
```

Then purge the page cache and, if the host has one, the object cache.

## Settings

There's no settings screen. Two switches, both in code:

**Turn it off without deleting the file.** Add to `wp-config.php`:

```php
define( 'GARRETTBUILDS_COMMENTS_OFF_DISABLE', true );
```

**Change which post types keep their comment setting.** The default is `product`. In a theme's
`functions.php` or another plugin:

```php
add_filter( 'garrettbuilds_comments_off_allowed_post_types', function ( $types ) {
	$types[] = 'course';
	return $types;
} );
```

Return an empty array to close comments everywhere, products included.

## Test After Install

Replace `example.com`, `123` and `/a-post/` with the site, a published post ID and that post's
path.

```sh
# Comment form: expect HTTP 403
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://example.com/wp-comments-post.php \
  -d 'comment_post_ID=123&author=Test&email=test@example.com&comment=test'

# Trackback to the post's trackback address: expect
# <error>1</error> and "Sorry, trackbacks are closed for this item."
curl -s -X POST 'https://example.com/a-post/trackback/' \
  -d 'url=https://example.org/&title=test&blog_name=test&excerpt=test'

# Pingback over XML-RPC: expect a fault (method not found)
curl -s -X POST https://example.com/xmlrpc.php -H 'Content-Type: text/xml' \
  -d '<?xml version="1.0"?><methodCall><methodName>pingback.ping</methodName><params><param><value><string>https://example.org/</string></value></param><param><value><string>https://example.com/?p=123</string></value></param></params></methodCall>'

# REST comment while logged out: expect HTTP 401
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://example.com/wp-json/wp/v2/comments \
  -H 'Content-Type: application/json' -d '{"post":123,"content":"test"}'

# Header gone: expect no X-Pingback line
curl -sI https://example.com/ | grep -i x-pingback

# Other REST routes still answer: expect HTTP 200
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/wp-json/
```

Then, logged in, check that these still work:

- A Note in the block editor
- A form on the site
- A product review, on WooCommerce sites

If a host's page cache answers one of the POST tests with a cached page, the cache answered
before WordPress did. Test again with a query string such as `?nocache=1` added to the URL.

## Development

`tests/smoke.php` loads the plugin with small stand-ins for WordPress functions and checks
every hook. It needs PHP only, no WordPress install.

```sh
php -l garrettbuilds-comments-off.php
php tests/smoke.php
```

GitHub Actions runs both on PHP 7.4, 8.0, 8.2 and 8.4 for every push. The smoke test checks the
plugin's own logic; the curl tests above check it on a real site.

## Remove

Delete the file from `wp-content/mu-plugins/`. Every post goes back to the comment and ping
setting stored in the database, unless `close-existing --yes` was run.

## Why a Must-Use Plugin

A must-use plugin loads on every request, can't be switched off from the Plugins screen by
accident, and needs no activation. That suits a rule every page should follow. The trade-off is
that it doesn't appear in the normal plugin list, so note it wherever the site's setup is
documented.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Built by [Garrett Nafzinger](https://garrettdigital.com) at Garrett Digital.
