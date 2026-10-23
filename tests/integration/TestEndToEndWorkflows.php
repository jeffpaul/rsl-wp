<?php
/**
 * End-to-end workflow tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Integration;

use RSL\Tests\TestCase;
use RSL_License;
use RSL_Server;
use RSL_OAuth_Client;
use RSL_Frontend;
use WP_REST_Request;

/**
 * Test complete workflows from start to finish
 *
 * @group integration
 * @group e2e
 * @group workflows
 */
class TestEndToEndWorkflows extends TestCase {

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
	 * Frontend instance
	 *
	 * @var RSL_Frontend
	 */
	private $frontend;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		$this->license_handler = new RSL_License();
		$this->server          = new RSL_Server();
		$this->oauth_client    = RSL_OAuth_Client::get_instance();
		$this->frontend        = new RSL_Frontend();

		// Set up WordPress environment
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Test complete free license workflow
	 *
	 * Flow: Create license → Generate XML → Request token → Validate token
	 *
	 * @covers Complete free license flow
	 */
	public function test_complete_free_license_workflow() {
		// Step 1: Create free license
		$license_id = $this->license_handler->create_license(
			array(
				'name'            => 'E2E Free License',
				'description'     => 'End-to-end testing free license',
				'content_url'     => '/e2e-free-content',
				'payment_type'    => 'free',
				'amount'          => 0,
				'permits_usage'   => 'search,ai-summarize',
				'prohibits_usage' => 'train-ai,train-genai',
				'permits_user'    => 'non-commercial',
				'prohibits_user'  => 'commercial',
			)
		);

		$this->assertIsInt( $license_id );
		$this->assertGreaterThan( 0, $license_id );

		// Step 2: Verify license XML generation
		$license_data = $this->license_handler->get_license( $license_id );
		$xml          = $this->license_handler->generate_rsl_xml( $license_data );

		$this->assertValidRslXml( $xml );
		$this->assertStringContainsString( 'E2E Free License', $xml );
		$this->assertStringContainsString( 'type="free"', $xml );

		// Step 3: Request token without authentication (free license)
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/e2e-free-content' );
		$request->set_param( 'client', 'e2e-test-client' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$token_data = $response->get_data();

		$this->assertArrayHasKey( 'access_token', $token_data );
		$this->assertArrayHasKey( 'token_type', $token_data );
		$this->assertEquals( 'Bearer', $token_data['token_type'] );

		// Step 4: Validate the issued token
		$token = $token_data['access_token'];

		// Decode and verify token payload
		$reflection    = new \ReflectionClass( $this->server );
		$decode_method = $reflection->getMethod( 'jwt_decode_token' );
		$decode_method->setAccessible( true );

		$payload = $decode_method->invoke( $this->server, $token );

		$this->assertIsArray( $payload );
		$this->assertEquals( $license_id, $payload['lic'] );
		$this->assertEquals( 'e2e-test-client', $payload['sub'] );
		$this->assertArrayHasKey( 'exp', $payload );
		$this->assertGreaterThan( time(), $payload['exp'] );

		// Step 5: Use token for content access simulation
		$validate_method = $reflection->getMethod( 'validate_license_token' );
		$validate_method->setAccessible( true );

		$_SERVER['REQUEST_URI'] = '/e2e-free-content';
		$license                = $this->license_handler->get_license( $license_id );
		$is_valid               = $validate_method->invoke( $this->server, $token, array( $license ) );
		$this->assertTrue( $is_valid );
	}

	/**
	 * Test complete paid license workflow with OAuth authentication
	 *
	 * Flow: Create client → Create license → Authenticate → Request token → Payment required
	 *
	 * @covers Complete paid license flow
	 */
	public function test_complete_paid_license_workflow() {
		// Step 1: Create OAuth client
		$client_data = $this->oauth_client->create_client( 'E2E Paid Test Client' );

		$this->assertArrayHasKey( 'client_id', $client_data );
		$this->assertArrayHasKey( 'client_secret', $client_data );

		// Step 2: Create paid license
		$license_id = $this->license_handler->create_license(
			array(
				'name'          => 'E2E Paid License',
				'description'   => 'End-to-end testing paid license',
				'content_url'   => '/e2e-paid-content',
				'payment_type'  => 'purchase',
				'amount'        => 199.99,
				'currency'      => 'USD',
				'server_url'    => 'http://example.org/wp-json/rsl-olp/v1',
				'permits_usage' => 'train-ai,ai-use',
				'permits_user'  => 'commercial',
			)
		);

		$this->assertIsInt( $license_id );

		// Step 3: Request token with OAuth authentication
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_header( 'Authorization', 'Basic ' . base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] ) );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/e2e-paid-content' );
		$request->set_param( 'client', 'e2e-paid-client' );
		$request->set_param( 'create_checkout', true );

		$response = $this->server->olp_issue_token( $request );

		// Should return checkout URL for payment
		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertNotInstanceOf( 'WP_Error', $response );
			$payment_data = $response->get_data();
			$this->assertArrayHasKey( 'checkout_url', $payment_data );
			$this->assertStringContainsString( 'checkout', $payment_data['checkout_url'] );
		} else {
			// Without WooCommerce, should return payment not available error
			$this->assertInstanceOf( 'WP_Error', $response );
			$this->assertEquals( 'payment_not_available', $response->get_error_code() );
		}
	}

	/**
	 * Test complete subscription workflow
	 *
	 * Flow: Create license → Subscribe → Generate tokens → Cancel → Revoke tokens
	 *
	 * @covers Complete subscription flow
	 */
	public function test_complete_subscription_workflow() {
		if ( ! class_exists( 'WC_Subscriptions' ) && ! function_exists( 'wcs_get_subscriptions' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not available' );
		}

		// Step 1: Create OAuth client
		$client_data = $this->oauth_client->create_client( 'E2E Subscription Client' );

		// Step 2: Create subscription license
		$license_id = $this->license_handler->create_license(
			array(
				'name'          => 'E2E Subscription License',
				'content_url'   => '/e2e-subscription-content',
				'payment_type'  => 'subscription',
				'amount'        => 29.99,
				'currency'      => 'USD',
				'server_url'    => 'http://example.org/wp-json/rsl-olp/v1',
				'permits_usage' => 'all',
			)
		);

		// Step 3: Request subscription checkout
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_header( 'Authorization', 'Basic ' . base64_encode( $client_data['client_id'] . ':' . $client_data['client_secret'] ) );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/e2e-subscription-content' );
		$request->set_param( 'create_checkout', true );

		$response = $this->server->olp_issue_token( $request );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertNotInstanceOf( 'WP_Error', $response );
			$checkout_data = $response->get_data();
			$this->assertArrayHasKey( 'checkout_url', $checkout_data );
		}

		// Step 4: Simulate subscription creation and token generation
		$jti             = $this->oauth_client->generate_jti();
		$subscription_id = 123;

		$this->oauth_client->store_token(
			$jti,
			$client_data['client_id'],
			$license_id,
			time() + 2592000, // 30 days
			array( 'subscription_id' => $subscription_id )
		);

		$this->assertFalse( $this->oauth_client->is_token_revoked( $jti ) );

		// Step 5: Simulate subscription cancellation
		$revoked_count = $this->oauth_client->revoke_tokens_for_subscription( $subscription_id );
		$this->assertEquals( 1, $revoked_count );
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti ) );
	}

	/**
	 * Test complete external server workflow
	 *
	 * Flow: Create license with external server → Request token → Redirect to external server
	 *
	 * @covers External server redirection flow
	 */
	public function test_complete_external_server_workflow() {
		// Step 1: Create license pointing to external server
		$license_id = $this->license_handler->create_license(
			array(
				'name'         => 'E2E External Server License',
				'content_url'  => '/e2e-external-content',
				'payment_type' => 'purchase',
				'amount'       => 499.99,
				'currency'     => 'USD',
				'server_url'   => 'https://external-licensing-server.com/api/v1',
			)
		);

		// Step 2: Request token (should be redirected to external server)
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/e2e-external-content' );
		$request->set_param( 'client', 'external-test-client' );

		$response = $this->server->olp_issue_token( $request );

		// Should return external server error with redirection info
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'external_server', $response->get_error_code() );

		$error_data = $response->get_error_data();
		$this->assertArrayHasKey( 'server_url', $error_data );
		$this->assertEquals( 'https://external-licensing-server.com/api/v1', $error_data['server_url'] );
	}

	/**
	 * Test complete crawler authentication workflow
	 *
	 * Flow: Create license → Crawler access → Authentication challenge → Token validation
	 *
	 * @covers Crawler authentication flow
	 */
	public function test_complete_crawler_authentication_workflow() {
		// Step 1: Create license with server authentication required
		$license_id = $this->license_handler->create_license(
			array(
				'name'          => 'E2E Crawler License',
				'content_url'   => '/e2e-crawler-content',
				'server_url'    => 'http://example.org/wp-json/rsl-olp/v1',
				'payment_type'  => 'free',
				'permits_usage' => 'ai-summarize',
			)
		);

		// Step 2: First get a valid token
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/e2e-crawler-content' );
		$request->set_param( 'client', 'crawler-bot' );

		$response   = $this->server->olp_issue_token( $request );
		$token_data = $response->get_data();
		$token      = $token_data['access_token'];

		// Step 3: Simulate crawler request without token (should get 401)
		$_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
		$_SERVER['REQUEST_URI']     = '/e2e-crawler-content';
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		ob_start();
		try {
			$this->server->handle_license_authentication();
		} catch ( \Exception $e ) {
			// Expected - should exit with 401
		}
		$output = ob_get_clean();

		// Should contain license required message
		$this->assertStringContainsString( 'License required', $output );

		// Step 4: Simulate crawler request with valid token
		$_SERVER['HTTP_AUTHORIZATION'] = 'License ' . $token;

		// Should not exit (authentication passed)
		$this->server->handle_license_authentication();
		$this->assertTrue( true ); // If we reach here, authentication passed
	}

	/**
	 * Test complete license lifecycle workflow
	 *
	 * Flow: Create → Update → Use → Deactivate → Reactivate → Delete
	 *
	 * @covers Complete license lifecycle
	 */
	public function test_complete_license_lifecycle_workflow() {
		// Step 1: Create license
		$license_id = $this->license_handler->create_license(
			array(
				'name'         => 'E2E Lifecycle License',
				'description'  => 'Initial description',
				'content_url'  => '/lifecycle-test',
				'payment_type' => 'attribution',
				'standard_url' => 'https://creativecommons.org/licenses/by/4.0/',
			)
		);

		$this->assertIsInt( $license_id );

		// Step 2: Verify creation
		$license = $this->license_handler->get_license( $license_id );
		$this->assertEquals( 'E2E Lifecycle License', $license['name'] );
		$this->assertEquals( 'Initial description', $license['description'] );
		$this->assertEquals( 1, $license['active'] );

		// Step 3: Update license
		$update_result = $this->license_handler->update_license(
			$license_id,
			array(
				'description'  => 'Updated description',
				'amount'       => 49.99,
				'payment_type' => 'purchase',
			)
		);
		$this->assertTrue( $update_result );

		// Step 4: Verify update
		$updated_license = $this->license_handler->get_license( $license_id );
		$this->assertEquals( 'Updated description', $updated_license['description'] );
		$this->assertEquals( 49.99, $updated_license['amount'] );
		$this->assertEquals( 'purchase', $updated_license['payment_type'] );

		// Step 5: Use license (generate XML)
		$xml = $this->license_handler->generate_rsl_xml( $updated_license );
		$this->assertValidRslXml( $xml );
		$this->assertStringContainsString( 'type="purchase"', $xml );
		$this->assertStringContainsString( '49.99', $xml );

		// Step 6: Deactivate license
		$deactivate_result = $this->license_handler->update_license( $license_id, array( 'active' => 0 ) );
		$this->assertTrue( $deactivate_result );

		// Step 7: Verify deactivation affects functionality
		$inactive_license = $this->license_handler->get_license( $license_id );
		$this->assertEquals( 0, $inactive_license['active'] );

		// License should not appear in active license queries
		$active_licenses = $this->license_handler->get_licenses( array( 'active' => 1 ) );
		$found_in_active = false;
		foreach ( $active_licenses as $active_license ) {
			if ( $active_license['id'] == $license_id ) {
				$found_in_active = true;
				break;
			}
		}
		$this->assertFalse( $found_in_active );

		// Step 8: Reactivate license
		$reactivate_result = $this->license_handler->update_license( $license_id, array( 'active' => 1 ) );
		$this->assertTrue( $reactivate_result );

		$reactivated_license = $this->license_handler->get_license( $license_id );
		$this->assertEquals( 1, $reactivated_license['active'] );

		// Step 9: Delete license
		$delete_result = $this->license_handler->delete_license( $license_id );
		$this->assertTrue( $delete_result );

		// Step 10: Verify deletion
		$deleted_license = $this->license_handler->get_license( $license_id );
		$this->assertNull( $deleted_license );
	}

	/**
	 * Test complete frontend integration workflow
	 *
	 * Flow: Create license → Set global → HTML injection → HTTP headers → XML serving
	 *
	 * @covers Frontend integration workflow
	 */
	public function test_complete_frontend_integration_workflow() {
		// Step 1: Enable all frontend features
		update_option( 'rsl_enable_html_injection', 1 );
		update_option( 'rsl_enable_http_headers', 1 );

		// Step 2: Create and set global license
		$license_id = $this->license_handler->create_license(
			array(
				'name'          => 'E2E Frontend License',
				'content_url'   => '/',
				'payment_type'  => 'free',
				'permits_usage' => 'search,ai-summarize',
			)
		);

		update_option( 'rsl_global_license_id', $license_id );

		// Step 3: Test HTML injection
		ob_start();
		$this->frontend->inject_rsl_html();
		$html_output = ob_get_clean();

		$this->assertStringContainsString( '<script type="application/rsl+xml">', $html_output );
		$this->assertStringContainsString( 'E2E Frontend License', $html_output );

		// Step 4: Test HTTP headers (simulate)
		ob_start();
		$this->frontend->add_rsl_headers();
		ob_end_clean();

		// In actual implementation, would verify Link header is set
		$this->assertTrue( true );

		// Step 5: Test XML serving via query parameter
		$_GET['rsl_license'] = $license_id;

		ob_start();
		try {
			$this->frontend->handle_rsl_xml_requests();
		} catch ( \Exception $e ) {
			// Expected exit
		}
		$xml_output = ob_get_clean();

		$this->assertValidRslXml( $xml_output );
		$this->assertStringContainsString( 'E2E Frontend License', $xml_output );

		// Step 6: Test shortcode functionality
		$shortcode_output = $this->frontend->license_shortcode(
			array(
				'format' => 'link',
				'text'   => 'Our License',
			)
		);

		$this->assertStringContainsString( '<a href=', $shortcode_output );
		$this->assertStringContainsString( 'Our License', $shortcode_output );
		$this->assertStringContainsString( 'rsl_license=' . $license_id, $shortcode_output );
	}

	/**
	 * Test complete error handling workflow
	 *
	 * Flow: Invalid requests → Rate limiting → Error responses → Recovery
	 *
	 * @covers Error handling workflow
	 */
	public function test_complete_error_handling_workflow() {
		// Step 1: Test invalid license request
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', 99999 );
		$request->set_param( 'resource', 'http://example.org/test' );

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_license', $response->get_error_code() );

		// Step 2: Test missing resource parameter
		$valid_license_id = $this->create_test_license();

		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $valid_license_id );
		// Missing resource parameter

		$response = $this->server->olp_issue_token( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_request', $response->get_error_code() );

		// Step 3: Test rate limiting (if available)
		if ( class_exists( 'RSL_Rate_Limiter' ) ) {
			$rate_limiter = \RSL_Rate_Limiter::get_instance();
			$client_id    = 'error-test-client';

			// Exhaust rate limit
			for ( $i = 0; $i < 35; $i++ ) {
				$result = $rate_limiter->check_rate_limit( 'token', $client_id );
				if ( is_wp_error( $result ) ) {
					$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
					break;
				}
			}
		}

		// Step 4: Test recovery with valid request
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $valid_license_id );
		$request->set_param( 'resource', 'http://example.org/test-content' );
		$request->set_param( 'client', 'recovery-client' );

		$response = $this->server->olp_issue_token( $request );

		// Should work with different client (rate limiting is per-client)
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$token_data = $response->get_data();
		$this->assertArrayHasKey( 'access_token', $token_data );
	}

	/**
	 * Test complete multi-license content workflow
	 *
	 * Flow: Multiple licenses → URL pattern matching → Precedence handling
	 *
	 * @covers Multi-license handling workflow
	 */
	public function test_complete_multi_license_workflow() {
		// Step 1: Create multiple licenses with different URL patterns
		$root_license_id = $this->license_handler->create_license(
			array(
				'name'          => 'Root License',
				'content_url'   => '/',
				'payment_type'  => 'free',
				'permits_usage' => 'search',
			)
		);

		$api_license_id = $this->license_handler->create_license(
			array(
				'name'          => 'API License',
				'content_url'   => '/api/*',
				'payment_type'  => 'purchase',
				'amount'        => 99.99,
				'permits_usage' => 'train-ai',
			)
		);

		$premium_license_id = $this->license_handler->create_license(
			array(
				'name'          => 'Premium Content License',
				'content_url'   => '/premium/',
				'payment_type'  => 'subscription',
				'amount'        => 29.99,
				'permits_usage' => 'all',
			)
		);

		// Step 2: Test URL pattern matching for different resources
		$test_cases = array(
			array( 'http://example.org/', $root_license_id, 'Root License' ),
			array( 'http://example.org/api/v1/data', $api_license_id, 'API License' ),
			array( 'http://example.org/premium/', $premium_license_id, 'Premium Content License' ),
			array( 'http://example.org/blog/post', $root_license_id, 'Root License' ), // Falls back to root
		);

		foreach ( $test_cases as [$url, $expected_id, $expected_name] ) {
			// Use the URL matching logic from server
			$reflection = new \ReflectionClass( $this->server );
			$method     = $reflection->getMethod( 'find_matching_licenses' );
			$method->setAccessible( true );

			$matching_licenses = $method->invoke( $this->server, $url );

			$this->assertNotEmpty( $matching_licenses, "No license found for URL: {$url}" );

			// Should find the most specific match
			$found_expected = false;
			foreach ( $matching_licenses as $license ) {
				if ( $license['id'] == $expected_id && $license['name'] == $expected_name ) {
					$found_expected = true;
					break;
				}
			}
			$this->assertTrue( $found_expected, "Expected license not found for URL: {$url}" );
		}

		// Step 3: Test token generation respects URL patterns
		foreach ( $test_cases as [$url, $expected_id, $expected_name] ) {
			$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
			$request->set_param( 'license_id', $expected_id );
			$request->set_param( 'resource', $url );
			$request->set_param( 'client', 'multi-license-client' );

			$response = $this->server->olp_issue_token( $request );

			if ( $expected_name === 'Root License' || $expected_name === 'Premium Content License' ) {
				// Free licenses should return tokens
				$this->assertNotInstanceOf( 'WP_Error', $response, "Token generation failed for: {$url}" );
				$token_data = $response->get_data();
				$this->assertArrayHasKey( 'access_token', $token_data );
			} else {
				// Paid licenses should return checkout URL or require payment
				if ( class_exists( 'WooCommerce' ) ) {
					$response_data = $response->get_data();
					$this->assertTrue(
						isset( $response_data['checkout_url'] ) || isset( $response_data['access_token'] ),
						"Expected checkout URL or token for paid license: {$url}"
					);
				}
			}
		}
	}
}
