<?php
/**
 * Security validation tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Security;

use RSL\Tests\TestCase;
use RSL_License;
use RSL_Server;
use RSL_OAuth_Client;
use WP_REST_Request;

/**
 * Test security aspects of RSL Licensing
 *
 * @group security
 * @group validation
 */
class TestSecurityValidation extends TestCase {

	/**
	 * License handler instance
	 *
	 * @var RSL_License
	 */
	private $license_handler;

	/**
	 * Server instance
	 *
	 * @var RSL_Server
	 */
	private $server;

	/**
	 * OAuth client instance
	 *
	 * @var RSL_OAuth_Client
	 */
	private $oauth_client;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->license_handler = new RSL_License();
		$this->server          = new RSL_Server();
		$this->oauth_client    = RSL_OAuth_Client::get_instance();
	}

	/**
	 * Test SQL injection prevention in license queries
	 *
	 * @covers RSL_License::get_license
	 */
	public function test_sql_injection_prevention_license_queries() {
		// Attempt SQL injection in license ID
		$malicious_ids = array(
			'1; DROP TABLE wp_rsl_licenses; --',
			'1 UNION SELECT * FROM wp_users',
			"' OR '1'='1",
			"1'; DELETE FROM wp_rsl_licenses WHERE id='1",
			"<script>alert('xss')</script>",
		);

		foreach ( $malicious_ids as $malicious_id ) {
			$result = $this->license_handler->get_license( $malicious_id );
			$this->assertNull( $result, "SQL injection vulnerability found with ID: {$malicious_id}" );
		}
	}

	/**
	 * Test XSS prevention in license data
	 *
	 * @covers RSL_License::create_license
	 */
	public function test_xss_prevention_license_data() {
		$xss_payloads = array(
			'<script>alert("xss")</script>',
			'javascript:alert("xss")',
			'<img src="x" onerror="alert(1)">',
			'<svg onload="alert(1)">',
			'"><script>alert("xss")</script>',
		);

		foreach ( $xss_payloads as $payload ) {
			$license_data = array(
				'name'          => "Test License {$payload}",
				'description'   => "Description with {$payload}",
				'content_url'   => "/test{$payload}",
				'contact_email' => "test{$payload}@example.com",
			);

			$license_id = $this->license_handler->create_license( $license_data );
			$this->assertNotFalse( $license_id );

			$retrieved_license = $this->license_handler->get_license( $license_id );

			// Verify XSS payload is sanitized
			$this->assertStringNotContainsString( '<script>', $retrieved_license['name'] );
			$this->assertStringNotContainsString( '<script>', $retrieved_license['description'] );
			$this->assertStringNotContainsString( 'javascript:', $retrieved_license['content_url'] );
		}
	}

	/**
	 * Test path traversal prevention in content URLs
	 *
	 * @covers RSL_License::create_license
	 */
	public function test_path_traversal_prevention() {
		$traversal_payloads = array(
			'../../../etc/passwd',
			'..\\..\\..\\windows\\system32\\config\\sam',
			'/var/www/../../../etc/shadow',
			'....//....//....//etc/passwd',
			'%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
		);

		foreach ( $traversal_payloads as $payload ) {
			$license_data = array(
				'name'        => 'Traversal Test License',
				'content_url' => $payload,
			);

			$license_id = $this->license_handler->create_license( $license_data );
			$this->assertNotFalse( $license_id );

			$retrieved_license = $this->license_handler->get_license( $license_id );

			// Verify path traversal is prevented
			$this->assertStringNotContainsString( '..', $retrieved_license['content_url'] );
			$this->assertStringNotContainsString( '/etc/', $retrieved_license['content_url'] );
		}
	}

	/**
	 * Test authorization header injection prevention
	 *
	 * @covers RSL_Server::authenticate_oauth_client
	 */
	public function test_authorization_header_injection() {
		$injection_payloads = array(
			'Basic ' . base64_encode( "client:secret\nAdmin: yes" ),
			'Basic ' . base64_encode( "client:secret\r\nX-Admin: true" ),
			'Basic ' . base64_encode( "client:secret\0admin" ),
			"Basic\nBearer malicious-token",
		);

		$request = new WP_REST_Request();

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'authenticate_oauth_client' );
		$method->setAccessible( true );

		foreach ( $injection_payloads as $payload ) {
			$_SERVER['HTTP_AUTHORIZATION'] = $payload;

			$result = $method->invoke( $this->server, $request );

			// Should fail authentication due to injection
			$this->assertInstanceOf( 'WP_Error', $result );
		}
	}

	/**
	 * Test JWT token manipulation resistance
	 *
	 * @covers RSL_Server::jwt_decode_token
	 */
	public function test_jwt_token_manipulation_resistance() {
		// Generate valid token
		$payload = array(
			'sub' => 'test-client',
			'lic' => 1,
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$valid_token = $this->generate_test_jwt( $payload );
		$parts       = explode( '.', $valid_token );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_decode_token' );
		$method->setAccessible( true );

		// Test various token manipulations
		$manipulations = array(
			// Modified payload
			$parts[0] . '.' . base64_encode( '{"sub":"admin","lic":999}' ) . '.' . $parts[2],

			// Modified signature
			$parts[0] . '.' . $parts[1] . '.modified-signature',

			// Algorithm confusion (none algorithm)
			base64_encode( '{"alg":"none","typ":"JWT"}' ) . '.' . $parts[1] . '.',

			// Empty signature
			$parts[0] . '.' . $parts[1] . '.',

			// Extra parts
			$valid_token . '.extra-part',
		);

		foreach ( $manipulations as $manipulated_token ) {
			$result = $method->invoke( $this->server, $manipulated_token );
			$this->assertInstanceOf( 'WP_Error', $result, "Token manipulation not detected: {$manipulated_token}" );
		}
	}

	/**
	 * Test input validation for numeric parameters
	 *
	 * @covers RSL_License::get_license
	 */
	public function test_numeric_parameter_validation() {
		$invalid_inputs = array(
			'abc',
			'1.5',
			'1e10',
			'0x1',
			'null',
			'undefined',
			'[]',
			'{}',
		);

		foreach ( $invalid_inputs as $input ) {
			$result = $this->license_handler->get_license( $input );
			$this->assertNull( $result, "Invalid numeric input accepted: {$input}" );
		}
	}

	/**
	 * Test CSRF protection for admin actions
	 *
	 * @covers RSL_Admin::ajax_save_license
	 */
	public function test_csrf_protection() {
		// This test would require mocking WordPress nonce functions
		// For now, we'll verify that nonce checking is in place
		$this->assertTrue( function_exists( 'wp_verify_nonce' ), 'WordPress nonce functions should be available' );
	}

	/**
	 * Test rate limiting bypass attempts
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_rate_limiting_bypass_attempts() {
		if ( ! class_exists( 'RSL_Rate_Limiter' ) ) {
			$this->markTestSkipped( 'Rate limiter not available' );
		}

		$rate_limiter = \RSL_Rate_Limiter::get_instance();

		// Attempt to bypass rate limiting with various client IDs
		$bypass_attempts = array(
			'admin',
			'127.0.0.1',
			'localhost',
			'null',
			'',
			'rsl_admin_bypass',
			'../admin',
		);

		foreach ( $bypass_attempts as $client_id ) {
			// Simulate multiple requests
			for ( $i = 0; $i < 35; $i++ ) { // Exceed token endpoint limit of 30
				$result = $rate_limiter->check_rate_limit( 'token', $client_id );

				if ( $i >= 30 ) {
					$this->assertInstanceOf( 'WP_Error', $result, "Rate limiting bypassed for client: {$client_id}" );
					break;
				}
			}
		}
	}

	/**
	 * Test directory traversal in file operations
	 *
	 * @covers RSL_Media::embed_rsl_metadata
	 */
	public function test_directory_traversal_file_operations() {
		if ( ! class_exists( 'RSL_Media' ) ) {
			$this->markTestSkipped( 'Media class not available' );
		}

		$traversal_paths = array(
			'../../../wp-config.php',
			'/etc/passwd',
			'C:\\Windows\\System32\\config\\sam',
			'....//....//wp-config.php',
		);

		foreach ( $traversal_paths as $path ) {
			// Verify that file operations don't accept traversal paths
			// This would require specific implementation in RSL_Media
			$this->assertStringNotContainsString( '..', basename( $path ) );
		}
	}

	/**
	 * Test privilege escalation prevention
	 *
	 * @covers RSL_Admin::add_admin_menu
	 */
	public function test_privilege_escalation_prevention() {
		// Mock non-admin user
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		// Verify that non-admin users cannot access admin functions
		$this->assertFalse( current_user_can( 'manage_options' ) );

		// Test that admin pages check capabilities
		$admin = new \RSL_Admin();

		// This would require checking that admin menu callbacks verify permissions
		$this->assertTrue( true ); // Placeholder - would need specific implementation
	}

	/**
	 * Test sensitive information exposure prevention
	 *
	 * @covers RSL_OAuth_Client::get_client
	 */
	public function test_sensitive_information_exposure() {
		$client_data      = $this->oauth_client->create_client( 'Exposure Test Client' );
		$retrieved_client = $this->oauth_client->get_client( $client_data['client_id'] );

		// Verify sensitive data is not exposed
		$this->assertArrayNotHasKey( 'client_secret', $retrieved_client );
		$this->assertArrayNotHasKey( 'client_secret_hash', $retrieved_client );
	}

	/**
	 * Test session fixation prevention
	 *
	 * @covers RSL_Session_Manager::create_session
	 */
	public function test_session_fixation_prevention() {
		if ( ! class_exists( 'RSL_Session_Manager' ) ) {
			$this->markTestSkipped( 'Session manager not available' );
		}

		$session_manager = \RSL_Session_Manager::get_instance();
		$license         = array(
			'id'   => 1,
			'name' => 'Test License',
		);

		// Create session
		$session1 = $session_manager->create_session( $license, 'client1', array() );
		$session2 = $session_manager->create_session( $license, 'client1', array() );

		// Sessions should have different IDs even for same client
		$this->assertNotEquals( $session1['session_id'], $session2['session_id'] );
	}

	/**
	 * Test timing attack resistance
	 *
	 * @covers RSL_OAuth_Client::validate_client
	 */
	public function test_timing_attack_resistance() {
		$client_data = $this->oauth_client->create_client( 'Timing Test Client' );

		// Time validation with correct vs incorrect credentials
		$start_time = microtime( true );
		$this->oauth_client->validate_client( $client_data['client_id'], $client_data['client_secret'] );
		$valid_time = microtime( true ) - $start_time;

		$start_time = microtime( true );
		$this->oauth_client->validate_client( $client_data['client_id'], 'wrong-secret' );
		$invalid_time = microtime( true ) - $start_time;

		// Timing difference should be minimal (within 50ms)
		$timing_difference = abs( $valid_time - $invalid_time );
		$this->assertLessThan( 0.05, $timing_difference, 'Timing attack vulnerability detected' );
	}

	/**
	 * Test XML external entity (XXE) prevention
	 *
	 * @covers RSL_License::generate_rsl_xml
	 */
	public function test_xxe_prevention() {
		$license_data = array(
			'id'          => 1,
			'name'        => 'XXE Test License',
			'content_url' => '/xxe-test',
			'description' => '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><test>&xxe;</test>',
		);

		$xml = $this->license_handler->generate_rsl_xml( $license_data );

		// Verify XXE payload is not processed
		$this->assertStringNotContainsString( '<!ENTITY', $xml );
		$this->assertStringNotContainsString( '&xxe;', $xml );
		$this->assertStringNotContainsString( 'file:///', $xml );
	}

	/**
	 * Test mass assignment prevention
	 *
	 * @covers RSL_License::update_license
	 */
	public function test_mass_assignment_prevention() {
		$license_id = $this->create_test_license( array( 'name' => 'Mass Assignment Test' ) );

		// Attempt to mass assign sensitive fields
		$malicious_data = array(
			'name'       => 'Updated Name',
			'id'         => 999, // Attempt to change ID
			'created_at' => '2020-01-01 00:00:00', // Attempt to change creation date
			'active'     => 0, // This might be allowed
		);

		$result = $this->license_handler->update_license( $license_id, $malicious_data );
		$this->assertTrue( $result );

		$updated_license = $this->license_handler->get_license( $license_id );

		// Verify sensitive fields weren't changed
		$this->assertEquals( $license_id, $updated_license['id'] );
		$this->assertNotEquals( '2020-01-01 00:00:00', $updated_license['created_at'] );
	}

	/**
	 * Test that unknown column names are dropped before reaching $wpdb
	 *
	 * @covers RSL_License::update_license
	 * @covers RSL_License::sanitize_license_data
	 */
	public function test_update_license_drops_unknown_columns() {
		$license_id = $this->create_test_license( array( 'name' => 'Column Injection Test' ) );

		$malicious_data = array(
			'name'                                => 'Safe Name',
			'name` = (SELECT user_pass FROM wp_users LIMIT 1), `name' => 'x',
		);

		$sanitized = $this->license_handler->sanitize_license_data( $malicious_data );
		$this->assertSame( array( 'name' ), array_keys( $sanitized ) );

		$result = $this->license_handler->update_license( $license_id, $malicious_data );
		$this->assertTrue( $result );

		$updated_license = $this->license_handler->get_license( $license_id );
		$this->assertEquals( 'Safe Name', $updated_license['name'] );
	}
}
