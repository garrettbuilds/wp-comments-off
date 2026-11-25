<?php
/**
 * Plugin Name:       Comments Off
 * Plugin URI:        https://github.com/garrettbuilds/wp-comments-off
 * Description:       Closes comments, trackbacks and pingbacks on every post type except an allowlist (WooCommerce products by default). Core hooks only, no settings, no database writes.
 * Version:           0.1.2
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Garrett Nafzinger
 * Author URI:        https://www.garrettdigital.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Install as a must-use plugin: copy this one file into wp-content/mu-plugins/
 * (create the folder if it does not exist). To switch it off without deleting
 * it, add this line to wp-config.php:
 *
 *     define( 'GARRETTBUILDS_COMMENTS_OFF_DISABLE', true );
 */

namespace GarrettBuilds\CommentsOff;

defined( 'ABSPATH' ) || exit;

if ( defined( 'GARRETTBUILDS_COMMENTS_OFF_DISABLE' ) && GARRETTBUILDS_COMMENTS_OFF_DISABLE ) {
	return;
}

// Guard against a second copy of this file (for example one in mu-plugins and one in plugins).
if ( defined( 'GARRETTBUILDS_COMMENTS_OFF_LOADED' ) ) {
	return;
}
define( 'GARRETTBUILDS_COMMENTS_OFF_LOADED', '0.1.2' );

// Late enough that a theme or plugin filtering at the default priority cannot reopen comments.
const PRIORITY = 99;

/**
 * Post types that keep their own comment setting. Pings stay closed on these too.
 *
 * Filter: garrettbuilds_comments_off_allowed_post_types
 *
 * @return string[]
 */
function allowed_post_types() {
	$types = apply_filters( 'garrettbuilds_comments_off_allowed_post_types', array( 'product' ) );
	if ( ! is_array( $types ) ) {
		return array();
	}

	// Keep only non-empty strings, so a bad filter return can't raise warnings.
	return array_values( array_filter( $types, function ( $type ) {
		return is_string( $type ) && '' !== $type;
	} ) );
}

/**
 * @param int|\WP_Post|null $post Post ID or object; null uses the current post.
 */
function is_allowed( $post ) {
	$type = get_post_type( $post );

	return is_string( $type ) && in_array( $type, allowed_post_types(), true );
}

/**
 * Comments: closed unless the post type is on the allowlist.
 *
 * @param bool              $open
 * @param int|\WP_Post|null $post_id
 */
function filter_comments_open( $open, $post_id = null ) {
	return is_allowed( $post_id ) ? $open : false;
}
add_filter( 'comments_open', __NAMESPACE__ . '\\filter_comments_open', PRIORITY, 2 );

// Trackbacks and pingbacks: closed everywhere.
add_filter( 'pings_open', '__return_false', PRIORITY );

/**
 * New posts start with trackbacks off. Read-time override only; nothing is saved.
 */
function closed_status() {
	return 'closed';
}
add_filter( 'pre_option_default_ping_status', __NAMESPACE__ . '\\closed_status', PRIORITY );

/**
 * Comment form (wp-comments-post.php). Core already refuses when comments_open() is false;
 * this backstop runs only if something reopened comments after our filter.
 *
 * @param int $post_id
 */
function block_comment_post( $post_id ) {
	if ( is_allowed( (int) $post_id ) ) {
		return;
	}
	wp_die(
		'Sorry, comments are closed for this item.',
		'Comment Submission Failure',
		array( 'response' => 403, 'back_link' => true )
	);
}
add_action( 'pre_comment_on_post', __NAMESPACE__ . '\\block_comment_post', PRIORITY );

/**
 * Trackbacks (wp-trackback.php). Runs before core's own pings_open() check and answers
 * with the standard trackback error, so the response is the same whichever check stops it.
 */
function block_trackback() {
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ) );
	}
	if ( function_exists( 'trackback_response' ) ) {
		trackback_response( 1, 'Sorry, trackbacks are closed for this item.' );
	}
	wp_die( 'Trackbacks are closed.', '', array( 'response' => 403 ) );
}
add_action( 'pre_trackback_post', __NAMESPACE__ . '\\block_trackback', 1 );

/**
 * XML-RPC: remove the two pingback methods. XML-RPC itself stays on for Jetpack and the apps.
 *
 * @param array $methods
 */
function filter_xmlrpc_methods( $methods ) {
	if ( is_array( $methods ) ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	}
	return $methods;
}
add_filter( 'xmlrpc_methods', __NAMESPACE__ . '\\filter_xmlrpc_methods', PRIORITY );

/**
 * Stop advertising the pingback endpoint.
 *
 * @param array $headers
 */
function filter_headers( $headers ) {
	if ( is_array( $headers ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}
add_filter( 'wp_headers', __NAMESPACE__ . '\\filter_headers', PRIORITY );

/**
 * REST API: refuse anonymous writes to /wp/v2/comments. Every other route, every read,
 * every logged-in request (block editor Notes) and new comments on allowlisted post
 * types (product reviews) are left to core's own checks.
 *
 * @param mixed            $result
 * @param \WP_REST_Server  $server
 * @param \WP_REST_Request $request
 * @return mixed
 */
function filter_rest_dispatch( $result, $server, $request ) {
	if ( null !== $result || ! $request instanceof \WP_REST_Request ) {
		return $result;
	}
	if ( ! preg_match( '#^/wp/v2/comments(?:/|$)#', (string) $request->get_route() ) ) {
		return $result;
	}
	$method = strtoupper( (string) $request->get_method() );
	if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
		return $result;
	}
	if ( is_user_logged_in() ) {
		return $result;
	}
	if ( 'POST' === $method && is_numeric( $request->get_param( 'post' ) ) && is_allowed( (int) $request->get_param( 'post' ) ) ) {
		return $result;
	}

	return new \WP_Error(
		'rest_comment_login_required',
		'Sorry, you must be logged in to comment.',
		array( 'status' => 401 )
	);
}
add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\\filter_rest_dispatch', PRIORITY, 3 );

/*
 * WP-CLI commands. Web requests never write to the database; only `close-existing --yes`
 * does, and only when someone runs it.
 */
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {

	/**
	 * Check and permanently close comments and pings.
	 */
	class Command {

		/** Rows in these statuses are skipped by both the counts and the update. */
		const SKIP_STATUSES = "('auto-draft','trash')";

		/**
		 * Show what Comments Off is doing on this site, and what is still stored as open.
		 *
		 * ## EXAMPLES
		 *
		 *     wp comments-off status
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function status( $args, $assoc_args ) {
			$allowed = allowed_post_types();
			\WP_CLI::log( 'Comments Off ' . GARRETTBUILDS_COMMENTS_OFF_LOADED . ' is active.' );
			\WP_CLI::log( 'Post types that keep their own comment setting: ' . ( $allowed ? implode( ', ', $allowed ) : '(none)' ) );
			\WP_CLI::log( 'Stored default_ping_status: ' . self::stored_ping_default() . ' (read as closed while this plugin runs)' );
			\WP_CLI::log( 'Stored default_comment_status: ' . (string) get_option( 'default_comment_status' ) );
			foreach ( array( 'comment_status', 'ping_status' ) as $column ) {
				$counts = self::count_open( $column );
				\WP_CLI::log( sprintf( 'Stored %s = open: %d', $column, array_sum( $counts ) ) );
				foreach ( $counts as $type => $n ) {
					\WP_CLI::log( sprintf( '  %s: %d', $type, $n ) );
				}
			}
			\WP_CLI::log( 'These stored values are overridden while the plugin runs. Run `wp comments-off close-existing` to see what a permanent close would change.' );
		}

		/**
		 * Permanently close comments and pings in the database, so they stay closed if this
		 * plugin is ever removed. Post types on the allowlist keep their comment setting.
		 *
		 * Without --yes it only reports what it would change. Purge the host's page cache
		 * afterward.
		 *
		 * ## OPTIONS
		 *
		 * [--yes]
		 * : Make the change. Back up the database first.
		 *
		 * ## EXAMPLES
		 *
		 *     wp comments-off close-existing
		 *     wp comments-off close-existing --yes
		 *
		 * @subcommand close-existing
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function close_existing( $args, $assoc_args ) {
			global $wpdb;

			$allowed      = allowed_post_types();
			$comment_open = self::count_open( 'comment_status' );
			foreach ( $allowed as $type ) {
				unset( $comment_open[ $type ] );
			}
			$ping_open = self::count_open( 'ping_status' );

			\WP_CLI::log( sprintf(
				'Would close comments on %d posts and pings on %d posts, and set default_ping_status (now %s) to closed.',
				array_sum( $comment_open ),
				array_sum( $ping_open ),
				self::stored_ping_default()
			) );

			if ( empty( $assoc_args['yes'] ) ) {
				\WP_CLI::log( 'Nothing changed. Add --yes to make the change.' );
				return;
			}

			$skip_types = '';
			if ( $allowed ) {
				$skip_types = $wpdb->prepare( ' AND post_type NOT IN (' . implode( ',', array_fill( 0, count( $allowed ), '%s' ) ) . ')', $allowed );
			}
			$live = ' AND post_status NOT IN ' . self::SKIP_STATUSES;

			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ((comment_status = 'open'{$skip_types}) OR ping_status = 'open'){$live}" );

			$comments = $wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed' WHERE comment_status = 'open'{$skip_types}{$live}" );
			$pings    = $wpdb->query( "UPDATE {$wpdb->posts} SET ping_status = 'closed' WHERE ping_status = 'open'{$live}" );

			// Clear only the posts that changed, so a persistent object cache serves the new values.
			foreach ( (array) $ids as $id ) {
				clean_post_cache( (int) $id );
			}

			if ( false === $comments || false === $pings ) {
				\WP_CLI::error( 'The database update failed: ' . $wpdb->last_error );
			}

			// update_option() compares against get_option(), which our override answers with
			// "closed", so it would skip the write. Remove the override for the write.
			remove_filter( 'pre_option_default_ping_status', __NAMESPACE__ . '\\closed_status', PRIORITY );
			update_option( 'default_ping_status', 'closed' );
			add_filter( 'pre_option_default_ping_status', __NAMESPACE__ . '\\closed_status', PRIORITY );

			\WP_CLI::success( sprintf(
				'Closed comments on %d posts and pings on %d posts. Stored default_ping_status is now %s. Purge the page cache.',
				(int) $comments,
				(int) $pings,
				self::stored_ping_default()
			) );
		}

		/**
		 * The stored value, read with the override removed.
		 */
		private static function stored_ping_default() {
			remove_filter( 'pre_option_default_ping_status', __NAMESPACE__ . '\\closed_status', PRIORITY );
			$value = (string) get_option( 'default_ping_status' );
			add_filter( 'pre_option_default_ping_status', __NAMESPACE__ . '\\closed_status', PRIORITY );
			return $value;
		}

		/**
		 * Counts posts whose stored status is still open, grouped by post type.
		 *
		 * @param string $column comment_status or ping_status.
		 * @return array<string,int>
		 */
		private static function count_open( $column ) {
			global $wpdb;
			if ( ! in_array( $column, array( 'comment_status', 'ping_status' ), true ) ) {
				return array();
			}
			// $column is checked against a fixed list above, so it is safe to interpolate.
			$rows = $wpdb->get_results(
				"SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts}
				 WHERE {$column} = 'open' AND post_status NOT IN " . self::SKIP_STATUSES . '
				 GROUP BY post_type ORDER BY n DESC',
				ARRAY_A
			);
			$out = array();
			foreach ( (array) $rows as $row ) {
				$out[ $row['post_type'] ] = (int) $row['n'];
			}
			return $out;
		}
	}

	\WP_CLI::add_command( 'comments-off', __NAMESPACE__ . '\\Command' );
}
