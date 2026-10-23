<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter for RSL API endpoints
 *
 * Implements token bucket rate limiting using WordPress transients
 * to prevent abuse of the OAuth and session endpoints.
 */
class RSL_Rate_Limiter {

	private static $instance = null;

	// Default rate limits (requests per minute)
	private $default_limits = array(
		'token'      => 30,      // 30 token requests per minute per client
		'introspect' => 100, // 100 introspection requests per minute per client
		'session'    => 20,     // 20 session requests per minute per client
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Allow customization of rate limits via filters
		$this->default_limits = apply_filters( 'rsl_rate_limits', $this->default_limits );
	}

	/**
	 * Check if request should be rate limited
	 *
	 * @param string $endpoint Endpoint name (token, introspect, session)
	 * @param string $client_id Client identifier for rate limiting
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited
	 */
	public function check_rate_limit( $endpoint, $client_id = null ) {
		$key    = $this->get_rate_limit_key( $endpoint, $client_id );
		$limit  = $this->get_rate_limit( $endpoint );
		$window = $this->get_time_window(); // 1 minute window

		// Get current request count
		$current_count = get_transient( $key );

		if ( $current_count === false ) {
			// First request in this window
			set_transient( $key, 1, $window );
			return true;
		}

		if ( $current_count >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf( 'Rate limit exceeded. Maximum %d requests per minute allowed.', $limit ),
				array(
					'status'  => 429,
					'headers' => array(
						'Retry-After'           => '60',
						'X-RateLimit-Limit'     => $limit,
						'X-RateLimit-Remaining' => 0,
						'X-RateLimit-Reset'     => time() + $window,
					),
				)
			);
		}

		// Increment counter
		set_transient( $key, $current_count + 1, $window );

		return true;
	}

	/**
	 * Add rate limit headers to response
	 *
	 * @param string $endpoint
	 * @param string $client_id
	 */
	public function add_rate_limit_headers( $endpoint, $client_id = null ) {
		$key           = $this->get_rate_limit_key( $endpoint, $client_id );
		$limit         = $this->get_rate_limit( $endpoint );
		$current_count = get_transient( $key );
		$current_count = $current_count ? $current_count : 0;
		$remaining     = max( 0, $limit - $current_count );
		$window        = $this->get_time_window();

		header( 'X-RateLimit-Limit: ' . $limit );
		header( 'X-RateLimit-Remaining: ' . $remaining );
		header( 'X-RateLimit-Reset: ' . ( time() + $window ) );
	}

	/**
	 * Generate rate limit cache key
	 *
	 * @param string $endpoint
	 * @param string $client_id
	 * @return string
	 */
	private function get_rate_limit_key( $endpoint, $client_id = null ) {
		$identifier   = $client_id ? $client_id : $this->get_client_identifier();
		$window_start = floor( time() / 60 ) * 60; // Round to minute boundary

		return sprintf( 'rsl_rate_limit_%s_%s_%d', $endpoint, md5( $identifier ), $window_start );
	}

	/**
	 * Get client identifier for rate limiting
	 * Uses the client IP address only. Client-controlled headers such as
	 * User-Agent are deliberately excluded so they cannot be rotated to
	 * obtain a fresh rate limit bucket.
	 *
	 * @return string
	 */
	private function get_client_identifier() {
		return $this->get_client_ip();
	}

	/**
	 * Get client IP address
	 *
	 * Uses REMOTE_ADDR by default. Forwarded headers (X-Forwarded-For,
	 * X-Real-IP) are only honored when REMOTE_ADDR is a trusted proxy, as
	 * configured via the `rsl_trusted_proxies` filter. In that case the
	 * right-most X-Forwarded-For entry that is not itself a trusted proxy
	 * is used, since entries to its left are client-supplied.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';

		if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return 'unknown';
		}

		/**
		 * Filter the list of trusted reverse proxy IP addresses.
		 *
		 * Forwarded client IP headers are only trusted when the request
		 * arrives directly from one of these addresses.
		 *
		 * @param array $trusted_proxies List of trusted proxy IP addresses.
		 */
		$trusted_proxies = (array) apply_filters( 'rsl_trusted_proxies', array() );

		if ( empty( $trusted_proxies ) || ! in_array( $remote_addr, $trusted_proxies, true ) ) {
			return $remote_addr;
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );

			// Walk from the right (closest hop) and return the first untrusted, valid IP.
			for ( $i = count( $ips ) - 1; $i >= 0; $i-- ) {
				if ( ! filter_var( $ips[ $i ], FILTER_VALIDATE_IP ) ) {
					break;
				}
				if ( ! in_array( $ips[ $i ], $trusted_proxies, true ) ) {
					return $ips[ $i ];
				}
			}
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$real_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
			if ( filter_var( $real_ip, FILTER_VALIDATE_IP ) ) {
				return $real_ip;
			}
		}

		return $remote_addr;
	}

	/**
	 * Get rate limit for endpoint
	 *
	 * @param string $endpoint
	 * @return int
	 */
	private function get_rate_limit( $endpoint ) {
		return isset( $this->default_limits[ $endpoint ] ) ?
				$this->default_limits[ $endpoint ] :
				$this->default_limits['token']; // Default fallback
	}

	/**
	 * Get time window in seconds (60 seconds = 1 minute)
	 *
	 * @return int
	 */
	private function get_time_window() {
		return apply_filters( 'rsl_rate_limit_window', 60 );
	}

	/**
	 * Clear rate limit for client (admin override)
	 *
	 * @param string $endpoint
	 * @param string $client_id
	 * @return bool
	 */
	public function clear_rate_limit( $endpoint, $client_id ) {
		$key = $this->get_rate_limit_key( $endpoint, $client_id );
		return delete_transient( $key );
	}

	/**
	 * Get rate limit status for client
	 *
	 * @param string $endpoint
	 * @param string $client_id
	 * @return array
	 */
	public function get_rate_limit_status( $endpoint, $client_id = null ) {
		$key           = $this->get_rate_limit_key( $endpoint, $client_id );
		$limit         = $this->get_rate_limit( $endpoint );
		$current_count = get_transient( $key );
		$current_count = $current_count ? $current_count : 0;
		$window        = $this->get_time_window();

		return array(
			'endpoint'       => $endpoint,
			'limit'          => $limit,
			'used'           => $current_count,
			'remaining'      => max( 0, $limit - $current_count ),
			'reset_time'     => time() + $window,
			'window_seconds' => $window,
		);
	}
}
