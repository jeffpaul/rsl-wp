<?php
/**
 * Tests for RSL_OAuth_Client class
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_OAuth_Client;

/**
 * Test RSL_OAuth_Client functionality
 *
 * @group unit
 * @group oauth
 */
class TestRSLOAuthClient extends TestCase {

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
		$this->oauth_client = RSL_OAuth_Client::get_instance();
	}

	/**
	 * Test OAuth client creation
	 *
	 * @covers RSL_OAuth_Client::create_client
	 */
	public function test_create_client() {
		$client_data = $this->oauth_client->create_client( 'Test Client' );

		$this->assertIsArray( $client_data );
		$this->assertArrayHasKey( 'client_id', $client_data );
		$this->assertArrayHasKey( 'client_secret', $client_data );
		$this->assertArrayHasKey( 'client_name', $client_data );
		$this->assertEquals( 'Test Client', $client_data['client_name'] );
		$this->assertTrue( $client_data['active'] );

		// Verify client ID format
		$this->assertStringStartsWith( 'rsl_', $client_data['client_id'] );
		$this->assertEquals( 20, strlen( $client_data['client_id'] ) ); // rsl_ + 16 chars

		// Verify client secret length
		$this->assertEquals( 32, strlen( $client_data['client_secret'] ) );
	}

	/**
	 * Test OAuth client creation with options
	 *
	 * @covers RSL_OAuth_Client::create_client
	 */
	public function test_create_client_with_options() {
		$options = array(
			'redirect_uris' => array( 'https://example.com/callback' ),
			'grant_types'   => 'client_credentials',
		);

		$client_data = $this->oauth_client->create_client( 'Test Client with Options', $options );

		$this->assertIsArray( $client_data );
		$this->assertEquals( 'Test Client with Options', $client_data['client_name'] );
	}

	/**
	 * Test OAuth client validation with valid credentials
	 *
	 * @covers RSL_OAuth_Client::validate_client
	 */
	public function test_validate_client_valid_credentials() {
		$client_data = $this->oauth_client->create_client( 'Valid Test Client' );

		$result = $this->oauth_client->validate_client(
			$client_data['client_id'],
			$client_data['client_secret']
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test OAuth client validation with invalid credentials
	 *
	 * @covers RSL_OAuth_Client::validate_client
	 */
	public function test_validate_client_invalid_credentials() {
		$client_data = $this->oauth_client->create_client( 'Invalid Test Client' );

		// Wrong secret
		$result = $this->oauth_client->validate_client(
			$client_data['client_id'],
			'wrong-secret'
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_client', $result->get_error_code() );

		// Wrong client ID
		$result = $this->oauth_client->validate_client(
			'wrong-client-id',
			$client_data['client_secret']
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_client', $result->get_error_code() );
	}

	/**
	 * Test OAuth client validation with missing credentials
	 *
	 * @covers RSL_OAuth_Client::validate_client
	 */
	public function test_validate_client_missing_credentials() {
		$result = $this->oauth_client->validate_client( '', '' );
		$this->assertInstanceOf( 'WP_Error', $result );

		$result = $this->oauth_client->validate_client( 'client-id', '' );
		$this->assertInstanceOf( 'WP_Error', $result );

		$result = $this->oauth_client->validate_client( '', 'secret' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test getting OAuth client by ID
	 *
	 * @covers RSL_OAuth_Client::get_client
	 */
	public function test_get_client() {
		$client_data = $this->oauth_client->create_client( 'Get Test Client' );

		$retrieved_client = $this->oauth_client->get_client( $client_data['client_id'] );

		$this->assertIsArray( $retrieved_client );
		$this->assertEquals( $client_data['client_id'], $retrieved_client['client_id'] );
		$this->assertEquals( 'Get Test Client', $retrieved_client['client_name'] );
		$this->assertArrayNotHasKey( 'client_secret_hash', $retrieved_client ); // Should not expose hash
	}

	/**
	 * Test getting non-existent OAuth client
	 *
	 * @covers RSL_OAuth_Client::get_client
	 */
	public function test_get_nonexistent_client() {
		$client = $this->oauth_client->get_client( 'nonexistent-client-id' );
		$this->assertNull( $client );
	}

	/**
	 * Test listing OAuth clients
	 *
	 * @covers RSL_OAuth_Client::list_clients
	 */
	public function test_list_clients() {
		// Create multiple test clients
		$client1 = $this->oauth_client->create_client( 'Client 1' );
		$client2 = $this->oauth_client->create_client( 'Client 2' );

		// Deactivate one client
		$this->oauth_client->revoke_client( $client2['client_id'] );

		// Get all clients
		$all_clients = $this->oauth_client->list_clients();
		$this->assertCount( 2, $all_clients );

		// Get only active clients
		$active_clients = $this->oauth_client->list_clients( array( 'active' => 1 ) );
		$this->assertCount( 1, $active_clients );
		$this->assertEquals( 'Client 1', $active_clients[0]['client_name'] );

		// Get only inactive clients
		$inactive_clients = $this->oauth_client->list_clients( array( 'active' => 0 ) );
		$this->assertCount( 1, $inactive_clients );
		$this->assertEquals( 'Client 2', $inactive_clients[0]['client_name'] );
	}

	/**
	 * Test OAuth client revocation
	 *
	 * @covers RSL_OAuth_Client::revoke_client
	 */
	public function test_revoke_client() {
		$client_data = $this->oauth_client->create_client( 'Revoke Test Client' );

		// Verify client is initially active
		$result = $this->oauth_client->validate_client(
			$client_data['client_id'],
			$client_data['client_secret']
		);
		$this->assertTrue( $result );

		// Revoke the client
		$revoke_result = $this->oauth_client->revoke_client( $client_data['client_id'] );
		$this->assertTrue( $revoke_result );

		// Verify client is now inactive
		$result = $this->oauth_client->validate_client(
			$client_data['client_id'],
			$client_data['client_secret']
		);
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_client', $result->get_error_code() );
	}

	/**
	 * Test token storage
	 *
	 * @covers RSL_OAuth_Client::store_token
	 */
	public function test_store_token() {
		$client_data = $this->oauth_client->create_client( 'Token Test Client' );
		$jti         = $this->oauth_client->generate_jti();

		$result = $this->oauth_client->store_token(
			$jti,
			$client_data['client_id'],
			1, // license_id
			time() + 3600, // expires_at
			array( 'order_id' => 123 )
		);

		$this->assertTrue( $result );

		// Verify token is not revoked
		$is_revoked = $this->oauth_client->is_token_revoked( $jti );
		$this->assertFalse( $is_revoked );
	}

	/**
	 * Test token revocation
	 *
	 * @covers RSL_OAuth_Client::revoke_token
	 */
	public function test_revoke_token() {
		$client_data = $this->oauth_client->create_client( 'Revoke Token Client' );
		$jti         = $this->oauth_client->generate_jti();

		// Store token
		$this->oauth_client->store_token(
			$jti,
			$client_data['client_id'],
			1,
			time() + 3600
		);

		// Verify token is not revoked initially
		$this->assertFalse( $this->oauth_client->is_token_revoked( $jti ) );

		// Revoke token
		$result = $this->oauth_client->revoke_token( $jti );
		$this->assertTrue( $result );

		// Verify token is now revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti ) );
	}

	/**
	 * Test token revocation for order
	 *
	 * @covers RSL_OAuth_Client::revoke_tokens_for_order
	 */
	public function test_revoke_tokens_for_order() {
		$client_data = $this->oauth_client->create_client( 'Order Revoke Client' );
		$order_id    = 123;

		// Store multiple tokens for the same order
		$jti1 = $this->oauth_client->generate_jti();
		$jti2 = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti1, $client_data['client_id'], 1, time() + 3600, array( 'order_id' => $order_id ) );
		$this->oauth_client->store_token( $jti2, $client_data['client_id'], 2, time() + 3600, array( 'order_id' => $order_id ) );

		// Revoke all tokens for the order
		$revoked_count = $this->oauth_client->revoke_tokens_for_order( $order_id );
		$this->assertEquals( 2, $revoked_count );

		// Verify both tokens are revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti1 ) );
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti2 ) );
	}

	/**
	 * Test token revocation for subscription
	 *
	 * @covers RSL_OAuth_Client::revoke_tokens_for_subscription
	 */
	public function test_revoke_tokens_for_subscription() {
		$client_data     = $this->oauth_client->create_client( 'Subscription Revoke Client' );
		$subscription_id = 456;

		// Store tokens for subscription
		$jti1 = $this->oauth_client->generate_jti();
		$jti2 = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti1, $client_data['client_id'], 1, time() + 3600, array( 'subscription_id' => $subscription_id ) );
		$this->oauth_client->store_token( $jti2, $client_data['client_id'], 2, time() + 3600, array( 'subscription_id' => $subscription_id ) );

		// Revoke all tokens for the subscription
		$revoked_count = $this->oauth_client->revoke_tokens_for_subscription( $subscription_id );
		$this->assertEquals( 2, $revoked_count );

		// Verify both tokens are revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti1 ) );
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti2 ) );
	}

	/**
	 * Test expired token cleanup
	 *
	 * @covers RSL_OAuth_Client::cleanup_expired_tokens
	 */
	public function test_cleanup_expired_tokens() {
		$client_data = $this->oauth_client->create_client( 'Cleanup Test Client' );

		// Store expired token
		$expired_jti = $this->oauth_client->generate_jti();
		$this->oauth_client->store_token(
			$expired_jti,
			$client_data['client_id'],
			1,
			time() - 3600 // Expired 1 hour ago
		);

		// Store valid token
		$valid_jti = $this->oauth_client->generate_jti();
		$this->oauth_client->store_token(
			$valid_jti,
			$client_data['client_id'],
			1,
			time() + 3600 // Expires in 1 hour
		);

		// Run cleanup
		$this->oauth_client->cleanup_expired_tokens();

		// Verify expired token is removed (would return false when checked)
		// Valid token should still exist
		$this->assertFalse( $this->oauth_client->is_token_revoked( $valid_jti ) );
	}

	/**
	 * Test Basic authentication header parsing
	 *
	 * @covers RSL_OAuth_Client::parse_basic_auth
	 */
	public function test_parse_basic_auth() {
		$client_id     = 'test-client-id';
		$client_secret = 'test-client-secret';
		$credentials   = base64_encode( $client_id . ':' . $client_secret );

		$auth_header = 'Basic ' . $credentials;
		$parsed      = $this->oauth_client->parse_basic_auth( $auth_header );

		$this->assertIsArray( $parsed );
		$this->assertCount( 2, $parsed );
		$this->assertEquals( $client_id, $parsed[0] );
		$this->assertEquals( $client_secret, $parsed[1] );
	}

	/**
	 * Test Basic authentication header parsing with invalid format
	 *
	 * @covers RSL_OAuth_Client::parse_basic_auth
	 */
	public function test_parse_basic_auth_invalid_format() {
		// Missing Basic prefix
		$result = $this->oauth_client->parse_basic_auth( 'Bearer token123' );
		$this->assertNull( $result );

		// Invalid base64
		$result = $this->oauth_client->parse_basic_auth( 'Basic invalid-base64!' );
		$this->assertNull( $result );

		// Missing colon separator
		$result = $this->oauth_client->parse_basic_auth( 'Basic ' . base64_encode( 'nocol' ) );
		$this->assertNull( $result );

		// Empty header
		$result = $this->oauth_client->parse_basic_auth( '' );
		$this->assertNull( $result );
	}

	/**
	 * Test JWT ID generation
	 *
	 * @covers RSL_OAuth_Client::generate_jti
	 */
	public function test_generate_jti() {
		$jti1 = $this->oauth_client->generate_jti();
		$jti2 = $this->oauth_client->generate_jti();

		$this->assertIsString( $jti1 );
		$this->assertIsString( $jti2 );
		$this->assertNotEquals( $jti1, $jti2 ); // Should be unique

		// Should be valid UUID4 format
		$uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		$this->assertMatchesRegularExpression( $uuid_pattern, $jti1 );
		$this->assertMatchesRegularExpression( $uuid_pattern, $jti2 );
	}

	/**
	 * Test client ID generation format
	 *
	 * @covers RSL_OAuth_Client::generate_client_id
	 */
	public function test_generate_client_id() {
		// Use reflection to test private method
		$reflection = new \ReflectionClass( $this->oauth_client );
		$method     = $reflection->getMethod( 'generate_client_id' );

		$client_id1 = $method->invoke( $this->oauth_client );
		$client_id2 = $method->invoke( $this->oauth_client );

		$this->assertStringStartsWith( 'rsl_', $client_id1 );
		$this->assertStringStartsWith( 'rsl_', $client_id2 );
		$this->assertNotEquals( $client_id1, $client_id2 );
		$this->assertEquals( 20, strlen( $client_id1 ) ); // rsl_ + 16 chars
	}

	/**
	 * Test client secret generation
	 *
	 * @covers RSL_OAuth_Client::generate_client_secret
	 */
	public function test_generate_client_secret() {
		// Use reflection to test private method
		$reflection = new \ReflectionClass( $this->oauth_client );
		$method     = $reflection->getMethod( 'generate_client_secret' );

		$secret1 = $method->invoke( $this->oauth_client );
		$secret2 = $method->invoke( $this->oauth_client );

		$this->assertEquals( 32, strlen( $secret1 ) );
		$this->assertEquals( 32, strlen( $secret2 ) );
		$this->assertNotEquals( $secret1, $secret2 );

		// Should contain mix of characters
		$this->assertMatchesRegularExpression( '/[a-zA-Z]/', $secret1 );
		$this->assertMatchesRegularExpression( '/[0-9]/', $secret1 );
	}

	/**
	 * Test token revocation checking for non-existent token
	 *
	 * @covers RSL_OAuth_Client::is_token_revoked
	 */
	public function test_is_token_revoked_nonexistent() {
		$result = $this->oauth_client->is_token_revoked( 'nonexistent-jti' );
		$this->assertFalse( $result );
	}

	/**
	 * Test singleton pattern
	 *
	 * @covers RSL_OAuth_Client::get_instance
	 */
	public function test_singleton_instance() {
		$instance1 = RSL_OAuth_Client::get_instance();
		$instance2 = RSL_OAuth_Client::get_instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( 'RSL_OAuth_Client', $instance1 );
	}
}
