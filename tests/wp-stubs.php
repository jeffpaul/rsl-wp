<?php
/**
 * Minimal WordPress function stubs for the PHPUnit-only test environment.
 *
 * These are intentionally small: tests that need richer behaviour should use
 * Brain Monkey expectations. Stubs only exist to keep class construction and
 * simple pure helpers from fataling.
 *
 * @package RSL_Licensing
 */

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) {
		return filter_var( (string) $value, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return filter_var( (string) $value, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = '' ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Test';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return false;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://example.org/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'get_feed_link' ) ) {
	function get_feed_link( $feed = '' ) {
		return 'http://example.org/?feed=' . $feed;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = isset( $args[1] ) ? $args[1] : '';
		} else {
			$params = array( $args[0] => isset( $args[1] ) ? $args[1] : '' );
			$url    = isset( $args[2] ) ? $args[2] : '';
		}

		$parts = parse_url( $url );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		$query = array_merge( $query, $params );
		$base  = $url;
		if ( isset( $parts['query'] ) ) {
			$base = str_replace( '?' . $parts['query'], '', $base );
		}
		return $base . ( strpos( $base, '?' ) === false ? '?' : '&' ) . http_build_query( $query );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		return add_query_arg( $name, 'testnonce', $actionurl );
	}
}

$GLOBALS['_rsl_test_cache'] = array();

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '' ) {
		return $GLOBALS['_rsl_test_cache'][ $group ][ $key ] ?? false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
		$GLOBALS['_rsl_test_cache'][ $group ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( $key, $value, $group = '', $expire = 0 ) {
		if ( isset( $GLOBALS['_rsl_test_cache'][ $group ][ $key ] ) ) {
			return false;
		}
		return wp_cache_set( $key, $value, $group, $expire );
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		unset( $GLOBALS['_rsl_test_cache'][ $group ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_cache_flush_group' ) ) {
	function wp_cache_flush_group( $group ) {
		unset( $GLOBALS['_rsl_test_cache'][ $group ] );
		return true;
	}
}

if ( ! function_exists( 'wp_cache_supports' ) ) {
	function wp_cache_supports( $feature ) {
		return 'flush_group' === $feature;
	}
}

if ( ! function_exists( 'wp_cache_set_last_changed' ) ) {
	function wp_cache_set_last_changed( $group ) {
		$GLOBALS['_rsl_test_cache'][ $group ]['last_changed'] = microtime( true );
	}
}

if ( ! function_exists( 'get_gmt_from_date' ) ) {
	function get_gmt_from_date( $date_string, $format = 'Y-m-d H:i:s' ) {
		$timestamp = strtotime( $date_string . ' UTC' );
		return $timestamp ? gmdate( $format, $timestamp ) : $date_string;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['_rsl_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['_rsl_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['_rsl_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'set_current_screen' ) ) {
	function set_current_screen( $screen = '' ) {
		return $screen;
	}
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
	function wp_set_current_user( $user_id ) {
		$GLOBALS['_rsl_test_current_user'] = $user_id;
		return $user_id;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['_rsl_test_current_user'] ?? 0;
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		return $response instanceof WP_REST_Response ? $response : new WP_REST_Response( $response );
	}
}

if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code ) {
		$GLOBALS['_rsl_test_status'] = $code;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $is_singular = false;
		public $is_home     = false;
		public $is_feed     = false;

		public function is_singular( $post_types = '' ) {
			return $this->is_singular;
		}

		public function is_home() {
			return $this->is_home;
		}

		public function is_feed( $feeds = '' ) {
			return $this->is_feed;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params = array();
		private $headers = array();

		public function __construct( $method = 'GET', $route = '' ) {
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function set_params( $params ) {
			$this->params = array_merge( $this->params, (array) $params );
		}

		public function set_body_params( $params ) {
			$this->set_params( $params );
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params() {
			return $this->params;
		}

		public function set_header( $key, $value ) {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( $key ) {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function set_data( $data ) {
			$this->data = $data;
		}

		public function get_status() {
			return $this->status;
		}

		public function set_status( $status ) {
			$this->status = $status;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
	}
}

if ( ! class_exists( 'WP_DB_Stub' ) ) {
	#[\AllowDynamicProperties]
	class WP_DB_Stub {
		public $prefix      = 'wp_';
		public $last_error  = '';
		public $insert_id   = 1;
		public $num_queries = 0;

		public function __call( $name, $arguments ) {
			if ( isset( $this->$name ) && $this->$name instanceof Closure ) {
				return call_user_func_array( $this->$name, $arguments );
			}

			if ( 'prepare' === $name ) {
				$query = array_shift( $arguments );
				return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $arguments );
			}

			if ( 'get_charset_collate' === $name ) {
				return '';
			}

			return null;
		}
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return 0;
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'rsl_log' ) ) {
	function rsl_log( $message, $level = 'info' ) {
		return null;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		return $GLOBALS['_rsl_test_is_singular'] ?? false;
	}
}

if ( ! function_exists( 'is_home' ) ) {
	function is_home() {
		return false;
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page() {
		return false;
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed( $feeds = '' ) {
		return false;
	}
}

if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		return false;
	}
}

if ( ! function_exists( 'is_single' ) ) {
	function is_single( $post = '' ) {
		return false;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return $GLOBALS['_rsl_test_queried_object_id'] ?? 0;
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return $GLOBALS['_rsl_test_queried_object_id'] ?? 0;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return 'http://example.org/?p=' . ( is_object( $post ) ? $post->ID : intval( $post ) );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		return 'post';
	}
}

$GLOBALS['_rsl_test_post_meta'] = array();

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['_rsl_test_post_meta'][ $post_id ] ?? array();
		}
		$value = $GLOBALS['_rsl_test_post_meta'][ $post_id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['_rsl_test_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return true;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'testnonce';
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
		return true;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		return array_merge( $pairs, (array) $atts );
	}
}

if ( ! defined( 'RSL_PLUGIN_VERSION' ) ) {
	define( 'RSL_PLUGIN_VERSION', '0.0.5' );
}
