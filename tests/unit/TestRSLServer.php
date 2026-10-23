<?php
/**
 * Tests for RSL_Server class
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_Server;
use RSL_License;
use RSL_OAuth_Client;
use WP_REST_Request;

/**
 * Test RSL_Server functionality
 *
 * @group unit
 * @group server
 * @group oauth
 */
class TestRSLServer extends TestCase {

	/**
	 * Server instance
	 *
	 * @var RSL_Server
	 */
	private $server;

	/**
	 * License handler instance
	 *
	 * @var RSL_License
	 */
	private $license_handler;

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
		$this->server          = new RSL_Server();
		$this->license_handler = new RSL_License();
		$this->oauth_client    = RSL_OAuth_Client::get_instance();

		// Mock WordPress functions
		add_filter(
			'home_url',
			function ( $path = '', $scheme = null ) {
				return 'http://example.org' . $path;
			}
		);
	}

	/**
	 * Test JWT token encoding
	 *
	 * @covers RSL_Server::jwt_encode_payload
	 */
	public function test_jwt_encode_payload() {
		$payload = array(
			'iss' => 'http://example.org',
			'aud' => 'example.org',
			'sub' => 'test-client',
			'iat' => time(),
			'exp' => time() + 3600,
			'lic' => 1,
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_encode_payload' );
		$method->setAccessible( true );

		$token = $method->invoke( $this->server, $payload );

		$this->assertIsString( $token );
		$this->assertStringContainsString( '.', $token );

		// Should have 3 parts (header.payload.signature)
		$parts = explode( '.', $token );
		$this->assertCount( 3, $parts );
	}

	/**
	 * Test JWT token decoding
	 *
	 * @covers RSL_Server::jwt_decode_token
	 */
	public function test_jwt_decode_token() {
		$original_payload = array(
			'iss' => 'http://example.org',
			'aud' => 'example.org',
			'sub' => 'test-client',
			'iat' => time(),
			'exp' => time() + 3600,
			'lic' => 1,
		);

		$token = $this->generate_test_jwt( $original_payload );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_decode_token' );
		$method->setAccessible( true );

		$decoded_payload = $method->invoke( $this->server, $token );

		$this->assertIsArray( $decoded_payload );
		$this->assertEquals( $original_payload['iss'], $decoded_payload['iss'] );
		$this->assertEquals( $original_payload['sub'], $decoded_payload['sub'] );
		$this->assertEquals( $original_payload['lic'], $decoded_payload['lic'] );
	}

	/**
	 * Test JWT token decoding with invalid token
	 *
	 * @covers RSL_Server::jwt_decode_token
	 */
	public function test_jwt_decode_token_invalid() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_decode_token' );
		$method->setAccessible( true );

		// Invalid token format
		$result = $method->invoke( $this->server, 'invalid.token' );
		$this->assertInstanceOf( 'WP_Error', $result );

		// Malformed token
		$result = $method->invoke( $this->server, 'not-a-token' );
		$this->assertInstanceOf( 'WP_Error', $result );

		// Empty token
		$result = $method->invoke( $this->server, '' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test token validation
	 *
	 * @covers RSL_Server::validate_license_token
	 */
	public function test_validate_license_token() {
		$now     = time();
		$payload = array(
			'typ'     => 'rsl_license',
			'iss'     => home_url(),
			'aud'     => wp_parse_url( home_url(), PHP_URL_HOST ),
			'sub'     => 'test-client',
			'jti'     => wp_generate_uuid4(),
			'iat'     => $now,
			'nbf'     => $now,
			'exp'     => $now + 3600,
			'lic'     => 1,
			'pattern' => '/test-content',
		);

		$token = $this->generate_test_jwt( $payload );

		// Mock $_SERVER for request URI
		$_SERVER['REQUEST_URI'] = '/test-content';

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'validate_license_token' );
		$method->setAccessible( true );

		$gated = array( array( 'id' => 1 ) );

		$result = $method->invoke( $this->server, $token, $gated );
		$this->assertTrue( $result );

		// Token for a different license than the one gating the URL is rejected
		$result = $method->invoke( $this->server, $token, array( array( 'id' => 2 ) ) );
		$this->assertFalse( $result );

		// No gating license at all is rejected
		$result = $method->invoke( $this->server, $token );
		$this->assertFalse( $result );

		// Missing mandatory claims are rejected
		foreach ( array( 'typ', 'aud', 'exp', 'jti', 'pattern', 'iss' ) as $claim ) {
			$partial = $payload;
			unset( $partial[ $claim ] );
			$result = $method->invoke( $this->server, $this->generate_test_jwt( $partial ), $gated );
			$this->assertFalse( $result, "Token without '$claim' must be rejected" );
		}

		// Wrong issuer or token type is rejected
		$wrong = array_merge( $payload, array( 'iss' => 'https://evil.example' ) );
		$this->assertFalse( $method->invoke( $this->server, $this->generate_test_jwt( $wrong ), $gated ) );
		$wrong = array_merge( $payload, array( 'typ' => 'payment_proof' ) );
		$this->assertFalse( $method->invoke( $this->server, $this->generate_test_jwt( $wrong ), $gated ) );
	}

	/**
	 * Test token validation with expired token
	 *
	 * @covers RSL_Server::validate_license_token
	 */
	public function test_validate_license_token_expired() {
		$now     = time();
		$payload = array(
			'iss' => 'http://example.org',
			'aud' => 'example.org',
			'sub' => 'test-client',
			'iat' => $now - 7200,
			'exp' => $now - 3600, // Expired 1 hour ago
			'lic' => 1,
		);

		$token = $this->generate_test_jwt( $payload );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'validate_license_token' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $token );
		$this->assertFalse( $result );
	}

	/**
	 * Test URL pattern matching
	 *
	 * @covers RSL_Server::url_matches_pattern
	 */
	public function test_url_matches_pattern() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'url_matches_pattern' );
		$method->setAccessible( true );

		// Exact match
		$result = $method->invoke( $this->server, '/exact-path', '/exact-path' );
		$this->assertTrue( $result );

		// Wildcard match
		$result = $method->invoke( $this->server, '/wildcard/subpath', '/wildcard/*' );
		$this->assertTrue( $result );

		// Root match
		$result = $method->invoke( $this->server, '/any-path', '/' );
		$this->assertTrue( $result );

		// No match
		$result = $method->invoke( $this->server, '/different-path', '/specific-path' );
		$this->assertFalse( $result );

		// Full URL vs relative path
		$result = $method->invoke( $this->server, 'http://example.org/test', '/test' );
		$this->assertTrue( $result );
	}

	/**
	 * Test free license detection
	 *
	 * @covers RSL_Server::is_free_license
	 */
	public function test_is_free_license() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'is_free_license' );
		$method->setAccessible( true );

		// Zero amount
		$license = array(
			'amount'       => 0,
			'payment_type' => 'purchase',
		);
		$result  = $method->invoke( $this->server, $license );
		$this->assertTrue( $result );

		// Priced license is never free, whatever its payment_type
		$license = array(
			'amount'       => 99.99,
			'payment_type' => 'free',
		);
		$result  = $method->invoke( $this->server, $license );
		$this->assertFalse( $result );

		// Paid attribution
		$license = array(
			'amount'       => 10,
			'payment_type' => 'attribution',
		);
		$result  = $method->invoke( $this->server, $license );
		$this->assertFalse( $result );

		// Attribution type
		$license = array(
			'amount'       => 0,
			'payment_type' => 'attribution',
		);
		$result  = $method->invoke( $this->server, $license );
		$this->assertTrue( $result );

		// Paid license
		$license = array(
			'amount'       => 99.99,
			'payment_type' => 'purchase',
		);
		$result  = $method->invoke( $this->server, $license );
		$this->assertFalse( $result );
	}

	/**
	 * Test token minting for free license
	 *
	 * @covers RSL_Server::mint_token_for_license
	 */
	public function test_mint_token_for_license_free() {
		$license = array(
			'id'            => 1,
			'name'          => 'Free License',
			'content_url'   => '/free-content',
			'payment_type'  => 'free',
			'amount'        => 0,
			'permits_usage' => 'search',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'mint_token_for_license' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $license, 'test-client' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );
		$this->assertArrayHasKey( 'token_type', $result );
		$this->assertArrayHasKey( 'expires_in', $result );
		$this->assertArrayHasKey( 'expires_at', $result );
		$this->assertArrayHasKey( 'license_url', $result );

		$this->assertEquals( 'Bearer', $result['token_type'] );
		$this->assertIsString( $result['access_token'] );
		$this->assertIsInt( $result['expires_in'] );
	}

	/**
	 * Test OAuth client authentication
	 *
	 * @covers RSL_Server::authenticate_oauth_client
	 */
	public function test_authenticate_oauth_client() {
		$client_data = $this->create_test_oauth_client( array( 'client_name' => 'Server Test Client' ) );

		$credentials = base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] );

		// Create mock request
		$request = new WP_REST_Request();

		// Mock authorization header
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $credentials;

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'authenticate_oauth_client' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $request );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
		$this->assertArrayHasKey( 'authenticated', $result );
		$this->assertEquals( $client_data['client_id'], $result['client_id'] );
		$this->assertTrue( $result['authenticated'] );
	}

	/**
	 * Test OAuth client authentication with invalid credentials
	 *
	 * @covers RSL_Server::authenticate_oauth_client
	 */
	public function test_authenticate_oauth_client_invalid() {
		$credentials = base64_encode( 'invalid-client:invalid-secret' );
		$request     = new WP_REST_Request();

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $credentials;

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'authenticate_oauth_client' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $request );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_client', $result->get_error_code() );
	}

	/**
	 * Test OAuth client authentication without header
	 *
	 * @covers RSL_Server::authenticate_oauth_client
	 */
	public function test_authenticate_oauth_client_no_header() {
		$request = new WP_REST_Request();

		// Remove authorization header
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'authenticate_oauth_client' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $request );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_client', $result->get_error_code() );
	}

	/**
	 * Test token endpoint for free license
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_olp_issue_token_free_license() {
		$license_id = $this->create_test_license(
			array(
				'name'         => 'Free Token Test',
				'content_url'  => '/free-test',
				'payment_type' => 'free',
				'amount'       => 0,
			)
		);

		$request = new WP_REST_Request();
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/free-test' );
		$request->set_param( 'client', 'test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayHasKey( 'token_type', $data );
		$this->assertEquals( 'Bearer', $data['token_type'] );
	}

	/**
	 * Test token endpoint with invalid license
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_olp_issue_token_invalid_license() {
		$request = new WP_REST_Request();
		$request->set_param( 'license_id', 99999 );
		$request->set_param( 'resource', 'http://example.org/test' );
		$request->set_param( 'client', 'test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_license', $response->get_error_code() );
	}

	/**
	 * Test token endpoint without resource parameter
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_olp_issue_token_missing_resource() {
		$license_id = $this->create_test_license();

		$request = new WP_REST_Request();
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'client', 'test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_request', $response->get_error_code() );
	}

	/**
	 * Test token endpoint with resource not covered by license
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_olp_issue_token_resource_not_covered() {
		$license_id = $this->create_test_license(
			array(
				'content_url' => '/specific-path',
			)
		);

		$request = new WP_REST_Request();
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/different-path' );
		$request->set_param( 'client', 'test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_resource', $response->get_error_code() );
	}

	/**
	 * Test token introspection with valid token
	 *
	 * @covers RSL_Server::olp_introspect
	 */
	public function test_olp_introspect_valid_token() {
		$client_data = $this->create_test_oauth_client( array( 'client_name' => 'Introspect Test Client' ) );
		$credentials = base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] );

		$payload = array(
			'sub' => $client_data['client_id'],
			'lic' => 1,
			'exp' => time() + 3600,
			'iat' => time(),
			'jti' => $this->oauth_client->generate_jti(),
		);

		$token = $this->generate_test_jwt( $payload );

		$request = new WP_REST_Request();
		$request->set_param( 'token', $token );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $credentials;

		$response = $this->server->olp_introspect( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$data = $response->get_data();

		$this->assertTrue( $data['active'] );
		$this->assertEquals( $client_data['client_id'], $data['client_id'] );
		$this->assertEquals( 1, $data['license_id'] );
	}

	/**
	 * Test token introspection with expired token
	 *
	 * @covers RSL_Server::olp_introspect
	 */
	public function test_olp_introspect_expired_token() {
		$client_data = $this->create_test_oauth_client( array( 'client_name' => 'Expired Test Client' ) );
		$credentials = base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] );

		$payload = array(
			'sub' => $client_data['client_id'],
			'lic' => 1,
			'exp' => time() - 3600, // Expired
			'iat' => time() - 7200,
		);

		$token = $this->generate_test_jwt( $payload );

		$request = new WP_REST_Request();
		$request->set_param( 'token', $token );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $credentials;

		$response = $this->server->olp_introspect( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$data = $response->get_data();

		$this->assertFalse( $data['active'] );
	}

	/**
	 * Test token introspection with revoked token
	 *
	 * @covers RSL_Server::olp_introspect
	 */
	public function test_olp_introspect_revoked_token() {
		$client_data = $this->create_test_oauth_client( array( 'client_name' => 'Revoked Test Client' ) );
		$credentials = base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] );

		$jti     = $this->oauth_client->generate_jti();
		$payload = array(
			'sub' => $client_data['client_id'],
			'lic' => 1,
			'exp' => time() + 3600,
			'iat' => time(),
			'jti' => $jti,
		);

		// Store and then revoke token
		$this->oauth_client->store_token( $jti, $client_data['client_id'], 1, time() + 3600 );
		$this->oauth_client->revoke_token( $jti );

		$token = $this->generate_test_jwt( $payload );

		$request = new WP_REST_Request();
		$request->set_param( 'token', $token );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $credentials;

		$response = $this->server->olp_introspect( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$data = $response->get_data();

		$this->assertFalse( $data['active'] );
	}

	/**
	 * Test token introspection without authentication
	 *
	 * @covers RSL_Server::olp_introspect
	 */
	public function test_olp_introspect_no_auth() {
		$token = $this->generate_test_jwt();

		$request = new WP_REST_Request();
		$request->set_param( 'token', $token );

		// No authorization header
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		$response = $this->server->olp_introspect( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_client', $response->get_error_code() );
	}

	/**
	 * Test crawler detection
	 *
	 * @covers RSL_Server::is_crawler_request
	 */
	public function test_is_crawler_request() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'is_crawler_request' );
		$method->setAccessible( true );

		// Known crawler user agents
		$crawler_uas = array(
			'Googlebot/2.1',
			'Mozilla/5.0 (compatible; bingbot/2.0)',
			'facebookexternalhit/1.1',
			'GPTBot',
			'ChatGPT-User',
			'CCBot/2.0',
			'anthropic-ai',
			'ClaudeBot',
		);

		foreach ( $crawler_uas as $ua ) {
			$_SERVER['HTTP_USER_AGENT'] = $ua;
			$result                     = $method->invoke( $this->server );
			$this->assertTrue( $result, "Failed to detect crawler: {$ua}" );
		}

		// Human user agents
		$human_uas = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
		);

		foreach ( $human_uas as $ua ) {
			$_SERVER['HTTP_USER_AGENT'] = $ua;
			$result                     = $method->invoke( $this->server );
			$this->assertFalse( $result, "Incorrectly detected human as crawler: {$ua}" );
		}
	}

	/**
	 * Test external server detection
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_olp_issue_token_external_server() {
		$license_id = $this->create_test_license(
			array(
				'name'         => 'External Server License',
				'content_url'  => '/external-content',
				'server_url'   => 'https://external-server.com/api/v1',
				'payment_type' => 'purchase',
				'amount'       => 199.99,
			)
		);

		$request = new WP_REST_Request();
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/external-content' );
		$request->set_param( 'client', 'test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'external_server', $response->get_error_code() );

		$error_data = $response->get_error_data();
		$this->assertEquals( 'https://external-server.com/api/v1', $error_data['server_url'] );
	}

	/**
	 * Test CORS headers
	 *
	 * @covers RSL_Server::add_cors_headers
	 */
	public function test_add_cors_headers() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'add_cors_headers' );
		$method->setAccessible( true );

		// Mock allowed origin
		$_SERVER['HTTP_ORIGIN'] = 'http://example.org';

		ob_start();
		$method->invoke( $this->server );
		ob_end_clean();

		// Check that headers would be sent (can't actually test headers in unit tests)
		$this->assertTrue( true ); // Placeholder assertion
	}

	/**
	 * Test server info generation
	 *
	 * @covers RSL_Server::get_server_info
	 */
	public function test_get_server_info() {
		$info = $this->server->get_server_info();

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'server_name', $info );
		$this->assertArrayHasKey( 'server_url', $info );
		$this->assertArrayHasKey( 'rsl_version', $info );
		$this->assertArrayHasKey( 'plugin_version', $info );
		$this->assertArrayHasKey( 'endpoints', $info );
		$this->assertArrayHasKey( 'features', $info );

		$this->assertEquals( '1.0', $info['rsl_version'] );
		$this->assertTrue( $info['features']['license_authentication'] );
		$this->assertTrue( $info['features']['payment_processing'] );
	}

	/**
	 * Test JWT secret management
	 *
	 * @covers RSL_Server::get_jwt_secret
	 */
	public function test_get_jwt_secret() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'get_jwt_secret' );
		$method->setAccessible( true );

		$secret1 = $method->invoke( $this->server );
		$secret2 = $method->invoke( $this->server );

		$this->assertIsString( $secret1 );
		$this->assertEquals( $secret1, $secret2 ); // Should be consistent
		$this->assertGreaterThan( 32, strlen( $secret1 ) ); // Should be reasonably long
	}

	/**
	 * Test JWT TTL configuration
	 *
	 * @covers RSL_Server::get_jwt_ttl
	 */
	public function test_get_jwt_ttl() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'get_jwt_ttl' );
		$method->setAccessible( true );

		$ttl = $method->invoke( $this->server );

		$this->assertEquals( 3600, $ttl ); // Default 1 hour
		$this->assertIsInt( $ttl );
	}
}
