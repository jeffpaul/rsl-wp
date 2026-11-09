<?php
/**
 * PHPUnit bootstrap file
 *
 * @package RSL_Licensing
 */

// Composer autoloader must be loaded first
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';

// Load Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Register shutdown function to tear down Brain Monkey
register_shutdown_function(
	function () {
		\Brain\Monkey\tearDown();
	}
);

// Define WordPress constants if not already defined
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Mock WordPress functions that are commonly used
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args = &$args;
		} else {
			parse_str( $args, $parsed_args );
		}

		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $parsed_args );
		}
		return $parsed_args;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		if ( $type === 'mysql' ) {
			return $gmt ? gmdate( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' );
		}
		return $gmt ? time() : time();
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		return 'http://example.org' . $path;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars ) {
			$chars .= '!@#$%^&*()';
		}
		if ( $extra_special_chars ) {
			$chars .= '-_ []{}<>~`+=,.;:/?|';
		}

		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
		}
		return $password;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return rand( $min, $max );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'wp_hash_password' ) ) {
	function wp_hash_password( $password ) {
		return password_hash( $password, PASSWORD_DEFAULT );
	}
}

if ( ! function_exists( 'wp_check_password' ) ) {
	function wp_check_password( $password, $hash, $user_id = '' ) {
		return password_verify( $password, $hash );
	}
}

// Global options storage for tests
global $_wp_test_options;
$_wp_test_options = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_wp_test_options;
		return isset( $_wp_test_options[ $option ] ) ? $_wp_test_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_wp_test_options;
		$_wp_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_wp_test_options;
		unset( $_wp_test_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value, $deprecated = '', $autoload = 'yes' ) {
		return update_option( $option, $value );
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		return true;
	}
}


if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors     = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->add( $code, $message, $data );
			}
		}

		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code() {
			if ( empty( $this->errors ) ) {
				return '';
			}
			return key( $this->errors );
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			if ( isset( $this->errors[ $code ] ) ) {
				return $this->errors[ $code ][0];
			}
			return '';
		}

		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}
	}
}

// Mock global $wpdb
global $wpdb;
if ( ! $wpdb ) {
	$wpdb = new WP_DB_Stub();
}

// Load mock classes for testing
require_once __DIR__ . '/mocks/MockRSLLicense.php';

// Simple autoloader for RSL classes - prefer mocks over real classes for testing
spl_autoload_register(
	function ( $class_name ) {
		// Handle RSL classes - try mocks first
		if ( strpos( $class_name, 'RSL_' ) === 0 ) {
			$mock_file = __DIR__ . '/mocks/Mock' . $class_name . '.php';
			if ( file_exists( $mock_file ) ) {
				require_once $mock_file;
				return;
			}

			// Fallback to real classes if available
			$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
			$file_path = dirname( __DIR__ ) . '/includes/' . $file_name;

			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}
);
