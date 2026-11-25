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

function get_post_types() { return array( 'post' => 'post', 'page' => 'page', 'product' => 'product' ); }
$GLOBALS['removed'] = array();
function remove_post_type_support( $t, $f ) { $GLOBALS['removed'][] = "$t:$f"; }
$GLOBALS['is_admin'] = true;
$GLOBALS['ajax'] = false;
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $k ) ); }
function wp_unslash( $v ) { return $v; }
$GLOBALS['registered'] = array( 'post', 'page', 'product' );
function get_post_type_object( $t ) { return in_array( $t, $GLOBALS['registered'], true ) ? (object) array( 'labels' => (object) array( 'name' => ucfirst( $t ) . 's' ) ) : null; }
function post_type_exists( $t ) { return in_array( $t, $GLOBALS['registered'], true ); }
function is_admin() { return $GLOBALS['is_admin']; }
class WP_REST_Response { private $d; function __construct( $d ) { $this->d = $d; } function get_data() { return $this->d; } function set_data( $d ) { $this->d = $d; } }
class FakeBar { public $removed = array(); function remove_node( $id ) { $this->removed[] = $id; } }

$GLOBALS['caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
function current_user_can( $c ) { return ! empty( $GLOBALS['caps'][ $c ] ); }
$GLOBALS['screen'] = (object) array( 'id' => 'options-discussion' );
function get_current_screen() { return $GLOBALS['screen']; }
$GLOBALS['multisite'] = false;
function is_multisite() { return $GLOBALS['multisite']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }

require dirname( __DIR__ ) . '/garrettbuilds-comments-off.php';
use function GarrettBuilds\CommentsOff\{admin_notice, file_label, filter_rest_post_type, filter_admin_bar, remove_admin_comment_support, filter_comments_open, filter_xmlrpc_methods, filter_headers, filter_rest_dispatch, closed_status, allowed_post_types};

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

$bar = new FakeBar(); filter_admin_bar( $bar );
check( 'admin bar bubble kept when products exist', array() === $bar->removed );
$GLOBALS['registered'] = array( 'post', 'page' ); $bar = new FakeBar(); filter_admin_bar( $bar );
check( 'admin bar bubble removed without WooCommerce', array( 'comments' ) === $bar->removed );
$GLOBALS['registered'] = array( 'post', 'page', 'product' );
remove_admin_comment_support();
check( 'admin: post and page lose comment UI, product keeps it', array( 'post:comments', 'post:trackbacks', 'page:comments', 'page:trackbacks' ) === $GLOBALS['removed'] );
$GLOBALS['removed'] = array(); $GLOBALS['is_admin'] = false; remove_admin_comment_support();
check( 'front end: post type support untouched', array() === $GLOBALS['removed'] );
$GLOBALS['removed'] = array(); $GLOBALS['is_admin'] = true; $GLOBALS['ajax'] = true; $_REQUEST['action'] = 'load_more_posts'; remove_admin_comment_support();
check( 'front-end AJAX: support untouched', array() === $GLOBALS['removed'] );
$_REQUEST['action'] = 'inline-save'; remove_admin_comment_support();
check( 'Quick Edit save: support removed so the row matches', 4 === count( $GLOBALS['removed'] ) );
$GLOBALS['ajax'] = false; unset( $_REQUEST['action'] );
$pt = (object) array( 'name' => 'post' );
$resp = filter_rest_post_type( new WP_REST_Response( array( 'supports' => array( 'editor' => array( array( 'notes' => true ) ), 'comments' => true, 'trackbacks' => true ) ) ), $pt );
$d = $resp->get_data();
check( 'block editor: Discussion support hidden, editor and Notes kept', array( 'editor' ) === array_keys( $d['supports'] ) );
$resp = filter_rest_post_type( new WP_REST_Response( array( 'supports' => array( 'comments' => true ) ) ), (object) array( 'name' => 'product' ) );
check( 'block editor: product keeps comments', isset( $resp->get_data()['supports']['comments'] ) );
check( 'rest_prepare_post_type tolerates odd input', 'x' === filter_rest_post_type( 'x', null ) );

$notice = function () { ob_start(); admin_notice(); return ob_get_clean(); };
$out = $notice();
check( 'notice on Discussion settings names the file and constant', false !== strpos( $out, 'garrettbuilds-comments-off.php' ) && false !== strpos( $out, 'GARRETTBUILDS_COMMENTS_OFF_DISABLE' ) && false !== strpos( $out, 'site-wide' ) );
check( 'notice names allowlisted types that exist, by label', false !== strpos( $out, 'on: Products.' ) );
$GLOBALS['registered'] = array( 'post', 'page' ); $out = $notice();
check( 'notice skips allowlisted types the site lacks', false === strpos( $out, 'still follow' ) );
$GLOBALS['registered'] = array( 'post', 'page', 'product' );
$GLOBALS['multisite'] = true; $GLOBALS['caps']['manage_network_options'] = false; $out = $notice();
check( 'multisite subsite admin: told to ask the network admin', false !== strpos( $out, 'ask your network administrator' ) && false === strpos( $out, 'wp-config.php' ) && false !== strpos( $out, 'network-wide' ) );
$GLOBALS['multisite'] = false; $GLOBALS['caps']['manage_network_options'] = true;
$GLOBALS['screen'] = (object) array( 'id' => 'dashboard' );
check( 'no notice on other screens', '' === $notice() );
$GLOBALS['screen'] = (object) array( 'id' => 'edit-comments' ); $GLOBALS['caps']['manage_options'] = false;
check( 'no notice for non-admins', '' === $notice() );
check( 'file label is relative to the WordPress root', 0 !== strpos( file_label(), '/' ) && 'garrettbuilds-comments-off.php' === basename( file_label() ) );

echo $fail ? "$fail failed\n" : "All passed\n";
exit( $fail ? 1 : 0 );
