<?php
/**
 * Base test case for RSL Licensing tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests;

use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Brain\Monkey;

/**
 * Base test case class
 */
class TestCase extends PHPUnit_TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset any global state
		$this->reset_globals();

		// Create test database tables (mocked)
		$this->create_test_tables();
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset global variables
	 */
	protected function reset_globals() {
		global $wpdb, $_wp_test_options;

		// Clear any cached data
		wp_cache_flush();

		// Reset WordPress options storage
		$_wp_test_options = array();
	}

	/**
	 * Create test database tables (mocked)
	 */
	protected function create_test_tables() {
		global $wpdb;

		// Mock the database operations instead of actually creating tables
		$wpdb->get_var = function ( $query ) {
			return 'wp_rsl_licenses'; // Mock table exists
		};

		$wpdb->insert = function ( $table, $data, $format = null ) {
			static $id = 1;
			return $id++;
		};

		$wpdb->get_row = function ( $query, $output = OBJECT ) {
			// Return mock data
			$mock_data = array(
				'id'           => 1,
				'name'         => 'Test License',
				'content_url'  => '/test',
				'payment_type' => 'free',
				'active'       => 1,
			);
			return $output === ARRAY_A ? $mock_data : (object) $mock_data;
		};

		$wpdb->get_results = function ( $query, $output = OBJECT ) {
			$mock_data = array(
				'id'           => 1,
				'name'         => 'Test License',
				'content_url'  => '/test',
				'payment_type' => 'free',
				'active'       => 1,
			);
			return array( $output === ARRAY_A ? $mock_data : (object) $mock_data );
		};

		$wpdb->update = function ( $table, $data, $where ) {
			return 1; // Success
		};

		$wpdb->delete = function ( $table, $where ) {
			return 1; // Success
		};

		$wpdb->query = function ( $query ) {
			return 1; // Success
		};
	}

	/**
	 * Create a test license
	 *
	 * @param array $args License arguments
	 * @return int License ID
	 */
	protected function create_test_license( $args = array() ) {
		$defaults = array(
			'name'            => 'Test License',
			'description'     => 'Test license description',
			'content_url'     => '/test-content',
			'server_url'      => '',
			'encrypted'       => 0,
			'permits_usage'   => 'search',
			'permits_user'    => 'non-commercial',
			'permits_geo'     => '',
			'prohibits_usage' => 'train-ai,train-genai',
			'prohibits_user'  => 'commercial',
			'prohibits_geo'   => '',
			'payment_type'    => 'free',
			'amount'          => 0,
			'currency'        => 'USD',
			'active'          => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		// Use the actual mock license handler
		if ( class_exists( 'RSL_License' ) ) {
			$license_handler = new \RSL_License();
			return $license_handler->create_license( $args );
		}

		// Fallback to simple counter
		static $license_id = 1;
		return $license_id++;
	}

	/**
	 * Create a test OAuth client
	 *
	 * @param array $args Client arguments
	 * @return array Client data
	 */
	protected function create_test_oauth_client( $args = array() ) {
		$defaults = array(
			'client_name' => 'Test Client',
		);

		$args = wp_parse_args( $args, $defaults );

		// Return mock client data
		static $client_id = 1;
		return array(
			'client_id'     => 'rsl_test_' . $client_id++,
			'client_secret' => 'test_secret_' . wp_generate_password( 32 ),
			'client_name'   => $args['client_name'],
			'active'        => true,
		);
	}

	/**
	 * Generate a test JWT token
	 *
	 * @param array $payload Token payload
	 * @return string JWT token
	 */
	protected function generate_test_jwt( $payload = array() ) {
		$defaults = array(
			'iss' => 'http://example.org',
			'aud' => 'example.org',
			'sub' => 'test-client',
			'iat' => time(),
			'exp' => time() + 3600,
			'lic' => 1,
		);

		$payload = wp_parse_args( $payload, $defaults );

		// Simple mock JWT token for testing
		$header          = base64_encode(
			json_encode(
				array(
					'alg' => 'HS256',
					'typ' => 'JWT',
				)
			)
		);
		$payload_encoded = base64_encode( json_encode( $payload ) );
		$signature       = base64_encode( 'mock-signature' );

		return $header . '.' . $payload_encoded . '.' . $signature;
	}

	/**
	 * Mock WordPress HTTP API response
	 *
	 * @param array $response_args Response arguments
	 */
	protected function mock_http_response( $response_args = array() ) {
		$defaults = array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{"success": true}',
			'headers'  => array(),
		);

		$response = wp_parse_args( $response_args, $defaults );

		add_filter(
			'pre_http_request',
			function () use ( $response ) {
				return $response;
			}
		);
	}

	/**
	 * Assert that a WordPress hook was called
	 *
	 * @param string $hook Hook name
	 * @param int    $times Number of times called (default 1)
	 */
	protected function assertHookCalled( $hook, $times = 1 ) {
		$this->assertEquals( $times, did_action( $hook ), "Hook '{$hook}' was not called {$times} time(s)" );
	}

	/**
	 * Assert that a string is valid JSON
	 *
	 * @param string $json JSON string
	 * @param string $message Error message
	 */
	protected function assertValidJson( $json, $message = '' ) {
		json_decode( $json );
		$this->assertEquals( JSON_ERROR_NONE, json_last_error(), $message );
	}

	/**
	 * Assert that a string is a valid RSL XML
	 *
	 * @param string $xml RSL XML string
	 * @param string $message Error message
	 */
	protected function assertValidRslXml( $xml, $message = '' ) {
		$dom    = new \DOMDocument();
		$result = $dom->loadXML( $xml );
		$this->assertTrue( $result, $message ?: 'XML should be valid' );

		// Check for RSL namespace
		$this->assertStringContains( 'https://rslstandard.org/rsl', $xml, $message );

		// Check for required elements
		$this->assertStringContains( '<rsl', $xml, $message );
		$this->assertStringContains( '<content', $xml, $message );
		$this->assertStringContains( '<license', $xml, $message );
	}

	/**
	 * Custom string contains assertion for compatibility
	 */
	protected function assertStringContains( $needle, $haystack, $message = '' ) {
		$this->assertThat(
			$haystack,
			$this->stringContains( $needle ),
			$message
		);
	}

	/**
	 * Custom string not contains assertion for compatibility
	 */
	protected function assertStringNotContains( $needle, $haystack, $message = '' ) {
		$this->assertThat(
			$haystack,
			$this->logicalNot( $this->stringContains( $needle ) ),
			$message
		);
	}
}
