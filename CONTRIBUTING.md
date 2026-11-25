# Contributing

Bug reports and small fixes are welcome. The plugin stays one file with core hooks only, no
settings screen and no database writes during web requests, so changes that add those are
unlikely to be merged.

## Before Opening a Pull Request

```sh
php -l garrettbuilds-comments-off.php
php tests/smoke.php
```

Add a check to `tests/smoke.php` for any new behavior, and a line to `CHANGELOG.md`. GitHub
Actions runs both commands on PHP 7.4, 8.0, 8.2 and 8.4.

## Reporting a Bug

Open an issue with the WordPress and PHP versions, the host, other plugins that touch comments,
and what you expected to happen. For a spam route the plugin misses, see `SECURITY.md` instead.
