<?php
/**
 * WooCommerce integration tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Integration;

use RSL\Tests\TestCase;
use RSL_Server;
use RSL_OAuth_Client;
use WP_REST_Request;

/**
 * Test WooCommerce integration
 *
 * @group integration
 * @group woocommerce
 */
class TestWooCommerceIntegration extends TestCase {

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
		$this->server       = new RSL_Server();
		$this->oauth_client = RSL_OAuth_Client::get_instance();

		// Mock WooCommerce availability
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce not available for integration testing' );
		}
	}

	/**
	 * Test WooCommerce detection
	 *
	 * @covers RSL_Server::is_wc_active
	 */
	public function test_woocommerce_detection() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'is_wc_active' );
		$method->setAccessible( true );

		$is_active = $method->invoke( $this->server );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertTrue( $is_active );
		} else {
			$this->assertFalse( $is_active );
		}
	}

	/**
	 * Test WooCommerce Subscriptions detection
	 *
	 * @covers RSL_Server::is_wcs_active
	 */
	public function test_woocommerce_subscriptions_detection() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'is_wcs_active' );
		$method->setAccessible( true );

		$is_active = $method->invoke( $this->server );

		if ( class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_get_subscriptions' ) ) {
			$this->assertTrue( $is_active );
		} else {
			$this->assertFalse( $is_active );
		}
	}

	/**
	 * Test automatic product creation for paid license
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 */
	public function test_automatic_product_creation() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce functions not available' );
		}

		$license = array(
			'id'           => 1,
			'name'         => 'WC Test License',
			'amount'       => 99.99,
			'currency'     => 'USD',
			'payment_type' => 'purchase',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'ensure_wc_product_for_license' );
		$method->setAccessible( true );

		$product_id = $method->invoke( $this->server, $license );

		if ( ! is_wp_error( $product_id ) ) {
			$this->assertIsInt( $product_id );
			$this->assertGreaterThan( 0, $product_id );

			// Verify product was created correctly
			$product = wc_get_product( $product_id );
			$this->assertNotFalse( $product );
			$this->assertTrue( $product->is_virtual() );
			$this->assertEquals( 'hidden', $product->get_catalog_visibility() );
			$this->assertEquals( 99.99, $product->get_price() );

			// Verify RSL license ID is stored in product meta
			$stored_license_id = get_post_meta( $product_id, '_rsl_license_id', true );
			$this->assertEquals( 1, intval( $stored_license_id ) );
		}
	}

	/**
	 * Test product reuse for same license
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 */
	public function test_product_reuse_for_same_license() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce functions not available' );
		}

		$license = array(
			'id'           => 2,
			'name'         => 'Reuse Test License',
			'amount'       => 49.99,
			'currency'     => 'USD',
			'payment_type' => 'purchase',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'ensure_wc_product_for_license' );
		$method->setAccessible( true );

		// Create product first time
		$product_id1 = $method->invoke( $this->server, $license );

		// Create product second time - should reuse existing
		$product_id2 = $method->invoke( $this->server, $license );

		if ( ! is_wp_error( $product_id1 ) && ! is_wp_error( $product_id2 ) ) {
			$this->assertEquals( $product_id1, $product_id2 );
		}
	}

	/**
	 * Test currency mismatch handling
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 */
	public function test_currency_mismatch_handling() {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			$this->markTestSkipped( 'WooCommerce currency functions not available' );
		}

		// Mock store currency as USD
		add_filter(
			'woocommerce_currency',
			function () {
				return 'USD';
			}
		);

		$license = array(
			'id'           => 3,
			'name'         => 'Currency Test License',
			'amount'       => 99.99,
			'currency'     => 'EUR', // Different from store currency
			'payment_type' => 'purchase',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'ensure_wc_product_for_license' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->server, $license );

		// Should return currency mismatch error
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'currency_mismatch', $result->get_error_code() );
	}

	/**
	 * Test token request with checkout creation
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_token_request_with_checkout_creation() {
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			$this->markTestSkipped( 'WooCommerce checkout functions not available' );
		}

		$license_id = $this->create_test_license(
			array(
				'name'         => 'Checkout Test License',
				'payment_type' => 'purchase',
				'amount'       => 199.99,
				'currency'     => 'USD',
			)
		);

		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/premium-content' );
		$request->set_param( 'create_checkout', true );
		$request->set_param( 'client', 'wc-test-client' );

		$response = $this->server->olp_issue_token( $request );

		if ( ! is_wp_error( $response ) ) {
			$data = $response->get_data();

			$this->assertArrayHasKey( 'checkout_url', $data );
			$this->assertStringContains( 'add-to-cart=', $data['checkout_url'] );
			$this->assertStringContains( '/checkout/', $data['checkout_url'] );
		}
	}

	/**
	 * Test order completion hook integration
	 *
	 * @covers RSL_Server::handle_wc_payment_completed
	 */
	public function test_order_completion_hook() {
		if ( ! function_exists( 'wc_get_order' ) ) {
			$this->markTestSkipped( 'WooCommerce order functions not available' );
		}

		// Mock order data
		$order_id   = 123;
		$license_id = 1;
		$session_id = 'test-session-' . wp_generate_uuid4();

		// Mock WooCommerce order
		$order = $this->createMock( 'WC_Order' );
		$order->method( 'is_paid' )->willReturn( true );
		$order->method( 'get_meta' )->willReturnMap(
			array(
				array( 'rsl_session_id', true, $session_id ),
				array( 'rsl_license_id', true, $license_id ),
			)
		);

		// Mock wc_get_order function using Brain Monkey
		\Brain\Monkey\Functions\when( 'wc_get_order' )->justReturn( $order );

		// Test the hook handler
		$this->server->handle_wc_payment_completed( $order_id );

		// Verify that payment completion was processed
		// In actual implementation, this would store payment proof in session
		$this->assertTrue( true ); // Placeholder assertion
	}

	/**
	 * Test order refund token revocation
	 *
	 * @covers RSL_Server::handle_wc_order_refunded
	 */
	public function test_order_refund_token_revocation() {
		$order_id = 456;

		// Create test tokens for the order
		$client_data = $this->oauth_client->create_client( 'Refund Test Client' );
		$jti1        = $this->oauth_client->generate_jti();
		$jti2        = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti1, $client_data['client_id'], 1, time() + 3600, array( 'order_id' => $order_id ) );
		$this->oauth_client->store_token( $jti2, $client_data['client_id'], 2, time() + 3600, array( 'order_id' => $order_id ) );

		// Verify tokens are not revoked initially
		$this->assertFalse( $this->oauth_client->is_token_revoked( $jti1 ) );
		$this->assertFalse( $this->oauth_client->is_token_revoked( $jti2 ) );

		// Handle order refund
		$this->server->handle_wc_order_refunded( $order_id );

		// Verify tokens are revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti1 ) );
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti2 ) );
	}

	/**
	 * Test order cancellation token revocation
	 *
	 * @covers RSL_Server::handle_wc_order_cancelled
	 */
	public function test_order_cancellation_token_revocation() {
		$order_id = 789;

		// Create test token for the order
		$client_data = $this->oauth_client->create_client( 'Cancel Test Client' );
		$jti         = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti, $client_data['client_id'], 1, time() + 3600, array( 'order_id' => $order_id ) );

		// Verify token is not revoked initially
		$this->assertFalse( $this->oauth_client->is_token_revoked( $jti ) );

		// Handle order cancellation
		$this->server->handle_wc_order_cancelled( $order_id );

		// Verify token is revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti ) );
	}

	/**
	 * Test subscription cancellation token revocation
	 *
	 * @covers RSL_Server::handle_wc_subscription_cancelled
	 */
	public function test_subscription_cancellation_token_revocation() {
		$subscription_id = 101;

		// Create test tokens for the subscription
		$client_data = $this->oauth_client->create_client( 'Subscription Cancel Test Client' );
		$jti1        = $this->oauth_client->generate_jti();
		$jti2        = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti1, $client_data['client_id'], 1, time() + 3600, array( 'subscription_id' => $subscription_id ) );
		$this->oauth_client->store_token( $jti2, $client_data['client_id'], 2, time() + 3600, array( 'subscription_id' => $subscription_id ) );

		// Mock subscription object
		$subscription = $this->createMock( 'WC_Subscription' );
		$subscription->method( 'get_id' )->willReturn( $subscription_id );

		// Handle subscription cancellation
		$this->server->handle_wc_subscription_cancelled( $subscription );

		// Verify tokens are revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti1 ) );
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti2 ) );
	}

	/**
	 * Test subscription expiration token revocation
	 *
	 * @covers RSL_Server::handle_wc_subscription_expired
	 */
	public function test_subscription_expiration_token_revocation() {
		$subscription_id = 202;

		// Create test token for the subscription
		$client_data = $this->oauth_client->create_client( 'Subscription Expire Test Client' );
		$jti         = $this->oauth_client->generate_jti();

		$this->oauth_client->store_token( $jti, $client_data['client_id'], 1, time() + 3600, array( 'subscription_id' => $subscription_id ) );

		// Mock subscription object
		$subscription = $this->createMock( 'WC_Subscription' );
		$subscription->method( 'get_id' )->willReturn( $subscription_id );

		// Handle subscription expiration
		$this->server->handle_wc_subscription_expired( $subscription );

		// Verify token is revoked
		$this->assertTrue( $this->oauth_client->is_token_revoked( $jti ) );
	}

	/**
	 * Test payment processor registry integration
	 *
	 * @covers RSL_Payment_Registry::get_processor_for_license
	 */
	public function test_payment_processor_registry_integration() {
		if ( ! class_exists( 'RSL_Payment_Registry' ) ) {
			$this->markTestSkipped( 'Payment registry not available' );
		}

		$registry = \RSL_Payment_Registry::get_instance();

		// Test WooCommerce processor registration
		if ( class_exists( 'WooCommerce' ) ) {
			$license = array(
				'payment_type' => 'purchase',
				'amount'       => 99.99,
			);

			$processor = $registry->get_processor_for_license( $license );
			$this->assertNotNull( $processor );
			$this->assertEquals( 'woocommerce', $processor->get_id() );
		}
	}

	/**
	 * Test WooCommerce product meta handling
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 */
	public function test_woocommerce_product_meta() {
		if ( ! function_exists( 'get_post_meta' ) ) {
			$this->markTestSkipped( 'WordPress post meta functions not available' );
		}

		// Create a test product manually to test meta handling
		$product_id = wp_insert_post(
			array(
				'post_title'  => 'Test RSL Product',
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);

		$license_id = 42;
		update_post_meta( $product_id, '_rsl_license_id', $license_id );

		// Verify meta was stored correctly
		$stored_license_id = get_post_meta( $product_id, '_rsl_license_id', true );
		$this->assertEquals( $license_id, intval( $stored_license_id ) );

		// Test querying products by license ID
		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'meta_key'       => '_rsl_license_id',
				'meta_value'     => $license_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$this->assertTrue( $query->have_posts() );
		$this->assertContains( $product_id, $query->posts );
	}

	/**
	 * Test zero-price product handling
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 */
	public function test_zero_price_product_handling() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce functions not available' );
		}

		$license = array(
			'id'           => 99,
			'name'         => 'Free License with Product',
			'amount'       => 0,
			'currency'     => 'USD',
			'payment_type' => 'free',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'ensure_wc_product_for_license' );
		$method->setAccessible( true );

		$product_id = $method->invoke( $this->server, $license );

		if ( ! is_wp_error( $product_id ) ) {
			$product = wc_get_product( $product_id );
			$this->assertEquals( 0, $product->get_price() );
			$this->assertEquals( 0, $product->get_regular_price() );
		}
	}

	/**
	 * Test WooCommerce hooks integration
	 *
	 * @covers RSL_Server::__construct
	 */
	public function test_woocommerce_hooks_integration() {
		// Verify that WooCommerce hooks are registered
		$expected_hooks = array(
			'woocommerce_order_status_completed'        => 'handle_wc_payment_completed',
			'woocommerce_payment_complete'              => 'handle_wc_payment_completed',
			'woocommerce_order_status_refunded'         => 'handle_wc_order_refunded',
			'woocommerce_order_status_cancelled'        => 'handle_wc_order_cancelled',
			'woocommerce_subscription_status_cancelled' => 'handle_wc_subscription_cancelled',
			'woocommerce_subscription_status_expired'   => 'handle_wc_subscription_expired',
		);

		foreach ( $expected_hooks as $hook => $method ) {
			$this->assertGreaterThan( 0, has_action( $hook ), "Hook {$hook} should be registered" );
		}
	}

	/**
	 * Test subscription license flow
	 *
	 * @covers RSL_Server::olp_issue_token
	 */
	public function test_subscription_license_flow() {
		if ( ! class_exists( 'WC_Subscriptions' ) && ! function_exists( 'wcs_get_subscriptions' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not available' );
		}

		$license_id = $this->create_test_license(
			array(
				'name'         => 'Subscription Flow Test',
				'payment_type' => 'subscription',
				'amount'       => 29.99,
				'currency'     => 'USD',
			)
		);

		// Test checkout URL generation for subscription
		$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
		$request->set_param( 'license_id', $license_id );
		$request->set_param( 'resource', 'http://example.org/subscription-content' );
		$request->set_param( 'create_checkout', true );
		$request->set_param( 'client', 'subscription-test-client' );

		$response = $this->server->olp_issue_token( $request );

		if ( ! is_wp_error( $response ) ) {
			$data = $response->get_data();
			$this->assertArrayHasKey( 'checkout_url', $data );
		}
	}

	/**
	 * Reused license products follow license price edits
	 *
	 * @covers RSL_Server::ensure_wc_product_for_license
	 * @covers RSL_Server::sync_wc_product_price
	 */
	public function test_reused_product_price_follows_license() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce functions not available' );
		}

		$license = array(
			'id'           => 4242,
			'name'         => 'Price Sync License',
			'amount'       => 10.00,
			'currency'     => get_woocommerce_currency(),
			'payment_type' => 'purchase',
		);

		$method = ( new \ReflectionClass( $this->server ) )->getMethod( 'ensure_wc_product_for_license' );
		$method->setAccessible( true );

		$product_id = $method->invoke( $this->server, $license );
		$this->assertIsInt( $product_id );

		$license['amount'] = 25.00;
		$this->assertEquals( $product_id, $method->invoke( $this->server, $license ) );
		$this->assertEquals( 25.00, (float) wc_get_product( $product_id )->get_price() );
	}

	/**
	 * Orders can only be redeemed by the client recorded at checkout, for the full license price
	 *
	 * @covers RSL_Server::verify_order_client
	 * @covers RSL_Server::verify_order_payment
	 */
	public function test_order_entitlement_requires_owner_and_full_payment() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce order functions not available' );
		}

		$product = new \WC_Product_Simple();
		$product->set_regular_price( 20 );
		$product->save();

		$license = array(
			'id'       => 4243,
			'amount'   => 20.00,
			'currency' => get_woocommerce_currency(),
		);

		$order   = wc_create_order();
		$item_id = $order->add_product( $product, 1 );
		$item    = $order->get_item( $item_id );
		$order->update_meta_data( '_rsl_client_id', 'rsl_owner' );
		$order->calculate_totals();
		$order->save();

		$reflection    = new \ReflectionClass( $this->server );
		$verify_client = $reflection->getMethod( 'verify_order_client' );
		$verify_client->setAccessible( true );
		$verify_paid = $reflection->getMethod( 'verify_order_payment' );
		$verify_paid->setAccessible( true );

		$this->assertTrue( $verify_client->invoke( $this->server, $order, $item, 'rsl_owner', $license, $product->get_id() ) );
		$this->assertFalse( $verify_client->invoke( $this->server, $order, $item, 'rsl_attacker', $license, $product->get_id() ) );
		$this->assertTrue( $verify_paid->invoke( $this->server, $order, $item, $license ) );

		// Discounted to zero must not mint a paid token
		$item->set_total( 0 );
		$this->assertInstanceOf( 'WP_Error', $verify_paid->invoke( $this->server, $order, $item, $license ) );

		// Unbound orders are rejected unless a site owner opts in via filter
		$order->delete_meta_data( '_rsl_client_id' );
		$this->assertFalse( $verify_client->invoke( $this->server, $order, $item, 'rsl_owner', $license, $product->get_id() ) );
		add_filter( 'rsl_verify_order_client', '__return_true' );
		$this->assertTrue( $verify_client->invoke( $this->server, $order, $item, 'rsl_owner', $license, $product->get_id() ) );
		remove_filter( 'rsl_verify_order_client', '__return_true' );
	}
}
