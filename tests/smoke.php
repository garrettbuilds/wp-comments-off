<?php
// Stub-based smoke test: loads the plugin with minimal WordPress stand-ins and checks each hook.
// Run: php tests/smoke.php
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['hooks'] = array();
$GLOBALS['logged_in'] = false;
$GLOBALS['types'] = array( 1 => 'post', 2 => 'product', 3 => 'attachment' );
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; return true; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return add_filter( $h, $cb, $p, $a ); }
function remove_filter( $h, $cb, $p = 10 ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function get_post_type( $p ) { return $GLOBALS['types'][ (int) $p ] ?? false; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_option( $k ) { return 'UTF-8'; }
class WP_Error { public $code; function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; } }
class WP_REST_Request { private $m, $r, $p; function __construct( $m, $r, $p = array() ) { $this->m = $m; $this->r = $r; $this->p = $p; } function get_method() { return $this->m; } function get_route() { return $this->r; } function get_param( $k ) { return $this->p[ $k ] ?? null; } }

require dirname( __DIR__ ) . '/garrettbuilds-comments-off.php';
use function GarrettBuilds\CommentsOff\{filter_comments_open, filter_xmlrpc_methods, filter_headers, filter_rest_dispatch, closed_status, allowed_post_types};

$fail = 0;
function check( $name, $ok ) { global $fail; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n"; if ( ! $ok ) $fail++; }

check( 'post comments closed', false === filter_comments_open( true, 1 ) );
check( 'attachment comments closed', false === filter_comments_open( true, 3 ) );
check( 'product keeps open', true === filter_comments_open( true, 2 ) );
check( 'product keeps closed', false === filter_comments_open( false, 2 ) );
check( 'pings filter is __return_false', in_array( '__return_false', $GLOBALS['hooks']['pings_open'], true ) );
check( 'default ping read as closed', 'closed' === closed_status() );
$m = filter_xmlrpc_methods( array( 'pingback.ping' => 1, 'pingback.extensions.getPingbacks' => 1, 'wp.getUsersBlogs' => 1 ) );
check( 'xmlrpc pingback removed, others kept', array( 'wp.getUsersBlogs' ) === array_keys( $m ) );
check( 'X-Pingback removed', array( 'A' => 1 ) === filter_headers( array( 'X-Pingback' => 'x', 'A' => 1 ) ) );

$r = function ( $m, $route, $p = array() ) { return filter_rest_dispatch( null, null, new WP_REST_Request( $m, $route, $p ) ); };
check( 'anon POST comment blocked', $r( 'POST', '/wp/v2/comments', array( 'post' => 1 ) ) instanceof WP_Error );
check( 'anon DELETE comment blocked', $r( 'DELETE', '/wp/v2/comments/5' ) instanceof WP_Error );
check( 'anon GET comments allowed', null === $r( 'GET', '/wp/v2/comments' ) );
check( 'anon POST product review passes to core', null === $r( 'POST', '/wp/v2/comments', array( 'post' => 2 ) ) );
check( 'other route untouched', null === $r( 'POST', '/mcp/v1/tools' ) );
check( 'lookalike route untouched', null === $r( 'POST', '/wp/v2/commentsx' ) );
check( 'earlier result passed through', 'x' === filter_rest_dispatch( 'x', null, new WP_REST_Request( 'POST', '/wp/v2/comments' ) ) );
$GLOBALS['logged_in'] = true;
check( 'logged-in POST (Notes) allowed', null === $r( 'POST', '/wp/v2/comments', array( 'post' => 1, 'type' => 'note' ) ) );
check( 'allowlist default', array( 'product' ) === allowed_post_types() );

echo $fail ? "$fail failed\n" : "All passed\n";
exit( $fail ? 1 : 0 );
