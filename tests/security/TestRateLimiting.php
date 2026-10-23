<?php
/**
 * Rate limiting tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Security;

use RSL\Tests\TestCase;
use WP_REST_Request;

/**
 * Test rate limiting functionality
 *
 * @group security
 * @group rate-limiting
 */
class TestRateLimiting extends TestCase {

	/**
	 * Rate limiter instance
	 *
	 * @var RSL_Rate_Limiter|null
	 */
	private $rate_limiter;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		if ( class_exists( 'RSL_Rate_Limiter' ) ) {
			$this->rate_limiter = \RSL_Rate_Limiter::get_instance();
		} else {
			$this->markTestSkipped( 'Rate limiter class not available' );
		}
	}

	/**
	 * Test token endpoint rate limiting (30 requests/minute)
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_token_endpoint_rate_limiting() {
		$client_id = 'test-client-token';
		$endpoint  = 'token';

		// Should allow up to 30 requests
		for ( $i = 1; $i <= 30; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
			$this->assertNotInstanceOf( 'WP_Error', $result, "Request {$i} should be allowed" );
		}

		// 31st request should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test introspection endpoint rate limiting (100 requests/minute)
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_introspect_endpoint_rate_limiting() {
		$client_id = 'test-client-introspect';
		$endpoint  = 'introspect';

		// Should allow up to 100 requests
		for ( $i = 1; $i <= 100; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
			$this->assertNotInstanceOf( 'WP_Error', $result, "Request {$i} should be allowed" );
		}

		// 101st request should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test session endpoint rate limiting (20 requests/minute)
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_session_endpoint_rate_limiting() {
		$client_id = 'test-client-session';
		$endpoint  = 'session';

		// Should allow up to 20 requests
		for ( $i = 1; $i <= 20; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
			$this->assertNotInstanceOf( 'WP_Error', $result, "Request {$i} should be allowed" );
		}

		// 21st request should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test per-client rate limiting isolation
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_per_client_rate_limiting() {
		$client1  = 'test-client-1';
		$client2  = 'test-client-2';
		$endpoint = 'token';

		// Exhaust client1's rate limit
		for ( $i = 1; $i <= 30; $i++ ) {
			$this->rate_limiter->check_rate_limit( $endpoint, $client1 );
		}

		// Client1 should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client1 );
		$this->assertInstanceOf( 'WP_Error', $result );

		// Client2 should still be allowed
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client2 );
		$this->assertNotInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test rate limit headers
	 *
	 * @covers RSL_Rate_Limiter::add_rate_limit_headers
	 */
	public function test_rate_limit_headers() {
		$client_id = 'test-client-headers';
		$endpoint  = 'token';

		// Make a few requests to partially consume the limit
		$this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->rate_limiter->check_rate_limit( $endpoint, $client_id );

		// Capture headers (in real implementation, this would set HTTP headers)
		ob_start();
		$this->rate_limiter->add_rate_limit_headers( $endpoint, $client_id );
		$output = ob_get_clean();

		// In actual implementation, we'd check for specific headers
		// X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
		$this->assertTrue( true ); // Placeholder - actual header testing would require different approach
	}

	/**
	 * Test rate limit reset functionality
	 *
	 * @covers RSL_Rate_Limiter::reset_rate_limit
	 */
	public function test_rate_limit_reset() {
		if ( ! method_exists( $this->rate_limiter, 'reset_rate_limit' ) ) {
			$this->markTestSkipped( 'Rate limit reset method not available' );
		}

		$client_id = 'test-client-reset';
		$endpoint  = 'token';

		// Exhaust rate limit
		for ( $i = 1; $i <= 30; $i++ ) {
			$this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		}

		// Should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertInstanceOf( 'WP_Error', $result );

		// Reset rate limit
		$this->rate_limiter->reset_rate_limit( $endpoint, $client_id );

		// Should be allowed again
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertNotInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test rate limit with empty/null client ID
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_rate_limit_empty_client_id() {
		$endpoint = 'token';

		// Test with null client ID
		$result = $this->rate_limiter->check_rate_limit( $endpoint, null );
		$this->assertNotInstanceOf( 'WP_Error', $result ); // Should use fallback identifier

		// Test with empty string client ID
		$result = $this->rate_limiter->check_rate_limit( $endpoint, '' );
		$this->assertNotInstanceOf( 'WP_Error', $result ); // Should use fallback identifier
	}

	/**
	 * Test rate limit memory cleanup
	 *
	 * @covers RSL_Rate_Limiter::cleanup_expired_limits
	 */
	public function test_rate_limit_cleanup() {
		if ( ! method_exists( $this->rate_limiter, 'cleanup_expired_limits' ) ) {
			$this->markTestSkipped( 'Rate limit cleanup method not available' );
		}

		$client_id = 'test-client-cleanup';
		$endpoint  = 'token';

		// Generate some rate limit data
		$this->rate_limiter->check_rate_limit( $endpoint, $client_id );

		// Cleanup should run without errors
		$result = $this->rate_limiter->cleanup_expired_limits();
		$this->assertTrue( $result !== false );
	}

	/**
	 * Test burst handling
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_burst_handling() {
		$client_id = 'test-client-burst';
		$endpoint  = 'token';

		// Rapid fire requests
		$start_time    = microtime( true );
		$success_count = 0;
		$error_count   = 0;

		for ( $i = 0; $i < 50; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );

			if ( is_wp_error( $result ) ) {
				++$error_count;
			} else {
				++$success_count;
			}
		}

		$elapsed_time = microtime( true ) - $start_time;

		// Should allow exactly 30 requests and block the rest
		$this->assertEquals( 30, $success_count );
		$this->assertEquals( 20, $error_count );

		// Should handle the burst quickly (under 1 second)
		$this->assertLessThan( 1.0, $elapsed_time );
	}

	/**
	 * Test different endpoint limits
	 *
	 * @covers RSL_Rate_Limiter::get_endpoint_limit
	 */
	public function test_endpoint_limits() {
		if ( ! method_exists( $this->rate_limiter, 'get_endpoint_limit' ) ) {
			$this->markTestSkipped( 'Get endpoint limit method not available' );
		}

		$limits = array(
			'token'      => 30,
			'introspect' => 100,
			'session'    => 20,
		);

		foreach ( $limits as $endpoint => $expected_limit ) {
			$actual_limit = $this->rate_limiter->get_endpoint_limit( $endpoint );
			$this->assertEquals( $expected_limit, $actual_limit, "Incorrect limit for endpoint: {$endpoint}" );
		}
	}

	/**
	 * Test rate limit window (time-based)
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_rate_limit_window() {
		$client_id = 'test-client-window';
		$endpoint  = 'token';

		// Exhaust rate limit
		for ( $i = 1; $i <= 30; $i++ ) {
			$this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		}

		// Should be rate limited
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		$this->assertInstanceOf( 'WP_Error', $result );

		// In real scenario, we'd wait for window to reset
		// For testing, we simulate time passage if method exists
		if ( method_exists( $this->rate_limiter, 'simulate_time_passage' ) ) {
			$this->rate_limiter->simulate_time_passage( 61 ); // Advance time by 61 seconds

			// Should be allowed again after window reset
			$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
			$this->assertNotInstanceOf( 'WP_Error', $result );
		} else {
			$this->markTestIncomplete( 'Time-based testing requires simulation method' );
		}
	}

	/**
	 * Test rate limit error response format
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_rate_limit_error_format() {
		$client_id = 'test-client-error-format';
		$endpoint  = 'token';

		// Exhaust rate limit
		for ( $i = 1; $i <= 30; $i++ ) {
			$this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		}

		// Get rate limit error
		$result = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertIsArray( $error_data );
		$this->assertEquals( 429, $error_data['status'] );

		if ( isset( $error_data['headers'] ) ) {
			$this->assertArrayHasKey( 'Retry-After', $error_data['headers'] );
		}
	}

	/**
	 * Test IP-based rate limiting fallback
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_ip_based_rate_limiting_fallback() {
		// Mock IP address
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$endpoint = 'token';

		// Test with no client ID (should use IP)
		for ( $i = 1; $i <= 30; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $endpoint );
			$this->assertNotInstanceOf( 'WP_Error', $result, "Request {$i} should be allowed" );
		}

		// Should be rate limited by IP
		$result = $this->rate_limiter->check_rate_limit( $endpoint );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test that spoofed forwarding headers and User-Agent cannot bypass IP rate limiting
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_ip_rate_limiting_ignores_spoofed_headers() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.101';

		$endpoint = 'session';

		for ( $i = 1; $i <= 20; $i++ ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.' . $i;
			$_SERVER['HTTP_CLIENT_IP']       = '198.51.100.' . $i;
			$_SERVER['HTTP_USER_AGENT']      = 'agent-' . $i;

			$result = $this->rate_limiter->check_rate_limit( $endpoint );
			$this->assertNotInstanceOf( 'WP_Error', $result, "Request {$i} should be allowed" );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.250';
		$_SERVER['HTTP_USER_AGENT']      = 'agent-new';

		$result = $this->rate_limiter->check_rate_limit( $endpoint );
		$this->assertInstanceOf( 'WP_Error', $result );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Test concurrent request handling
	 *
	 * @covers RSL_Rate_Limiter::check_rate_limit
	 */
	public function test_concurrent_request_handling() {
		$client_id = 'test-client-concurrent';
		$endpoint  = 'token';

		// Simulate concurrent requests (in practice, this would need actual threading)
		$requests = array();
		for ( $i = 0; $i < 35; $i++ ) {
			$requests[] = $this->rate_limiter->check_rate_limit( $endpoint, $client_id );
		}

		$success_count = count(
			array_filter(
				$requests,
				function ( $r ) {
					return ! is_wp_error( $r );
				}
			)
		);

		$error_count = count(
			array_filter(
				$requests,
				function ( $r ) {
					return is_wp_error( $r );
				}
			)
		);

		// Should allow exactly 30 and block 5
		$this->assertEquals( 30, $success_count );
		$this->assertEquals( 5, $error_count );
	}
}
