<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Session Manager
 *
 * Manages payment sessions using MCP-inspired patterns.
 * Sessions are stored server-side and polled by AI agents.
 */
class RSL_Session_Manager {

	private static $instance = null;
	private $session_ttl     = 3600; // 1 hour default

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Clean up expired sessions periodically
		add_action( 'wp_scheduled_delete', array( $this, 'cleanup_expired_sessions' ) );
	}

	/**
	 * Create a new payment session
	 *
	 * @param array  $license License data
	 * @param string $client Client identifier
	 * @param array  $options Additional options
	 * @return array Session data
	 */
	public function create_session( $license, $client, $options = array() ) {
		$session_id = wp_generate_uuid4();
		$now        = time();

		$session_data = array(
			'id'             => $session_id,
			'license_id'     => $license['id'],
			'client'         => $client,
			'status'         => 'created',
			'created_at'     => $now,
			'updated_at'     => $now,
			'expires_at'     => $now + $this->session_ttl,
			'checkout_url'   => null,
			'proof'          => null,
			'processor_id'   => null,
			'processor_data' => array(),
			'options'        => $options,
		);

		// Store session server-side (MCP pattern)
		update_option( 'rsl_session_' . $session_id, $session_data, false );

		return array(
			'session_id'  => $session_id,
			'status'      => 'created',
			'polling_url' => rest_url( 'rsl-olp/v1/session/' . $session_id ),
			'expires_at'  => gmdate( 'c', $session_data['expires_at'] ),
		);
	}

	/**
	 * Get session by ID
	 *
	 * @param string $session_id
	 * @return array|null
	 */
	public function get_session( $session_id ) {
		$session = get_option( 'rsl_session_' . $session_id );

		if ( ! $session ) {
			return null;
		}

		// Check if expired
		if ( time() > $session['expires_at'] ) {
			$this->delete_session( $session_id );
			return null;
		}

		return $session;
	}

	/**
	 * Update session data
	 *
	 * @param string $session_id
	 * @param array  $updates
	 * @return bool
	 */
	public function update_session( $session_id, $updates ) {
		$session = $this->get_session( $session_id );
		if ( ! $session ) {
			return false;
		}

		$session               = array_merge( $session, $updates );
		$session['updated_at'] = time();

		update_option( 'rsl_session_' . $session_id, $session, false );
		return true;
	}

	/**
	 * Update session status
	 *
	 * @param string $session_id
	 * @param string $status
	 * @param array  $data Additional data to store
	 * @return bool
	 */
	public function update_session_status( $session_id, $status, $data = array() ) {
		$updates = array_merge( array( 'status' => $status ), $data );
		return $this->update_session( $session_id, $updates );
	}

	/**
	 * Set checkout URL for session
	 *
	 * @param string $session_id
	 * @param string $checkout_url
	 * @param string $processor_id
	 * @return bool
	 */
	public function set_checkout_url( $session_id, $checkout_url, $processor_id ) {
		return $this->update_session(
			$session_id,
			array(
				'status'       => 'awaiting_payment',
				'checkout_url' => $checkout_url,
				'processor_id' => $processor_id,
			)
		);
	}

	/**
	 * Store payment proof for session
	 *
	 * @param string $session_id
	 * @param string $proof Signed payment confirmation
	 * @return bool
	 */
	public function store_payment_proof( $session_id, $proof ) {
		return $this->update_session(
			$session_id,
			array(
				'status' => 'proof_ready',
				'proof'  => $proof,
			)
		);
	}

	/**
	 * Mark session as completed
	 *
	 * @param string $session_id
	 * @return bool
	 */
	public function complete_session( $session_id ) {
		return $this->update_session_status( $session_id, 'completed' );
	}

	/**
	 * Delete session
	 *
	 * @param string $session_id
	 * @return bool
	 */
	public function delete_session( $session_id ) {
		return delete_option( 'rsl_session_' . $session_id );
	}

	/**
	 * Get session status for API response
	 *
	 * @param string $session_id
	 * @return array|WP_Error
	 */
	public function get_session_status( $session_id ) {
		$session = $this->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'session_not_found', 'Session not found or expired', array( 'status' => 404 ) );
		}

		$response = array(
			'session_id' => $session_id,
			'status'     => $session['status'],
			'created_at' => gmdate( 'c', $session['created_at'] ),
			'expires_at' => gmdate( 'c', $session['expires_at'] ),
		);

		// Add status-specific data
		switch ( $session['status'] ) {
			case 'created':
				$response['message'] = 'Session created, preparing checkout';
				break;

			case 'awaiting_payment':
				$response['checkout_url'] = $session['checkout_url'];
				$response['message']      = 'Payment required';
				break;

			case 'proof_ready':
				$response['signed_proof'] = $session['proof'];
				$response['message']      = 'Payment confirmed, use signed_proof to get token';
				break;

			case 'completed':
				$response['message'] = 'Session completed successfully';
				break;

			case 'failed':
				$response['message'] = 'Payment failed';
				break;
		}

		return $response;
	}

	/**
	 * Clean up expired sessions
	 */
	public function cleanup_expired_sessions() {
		global $wpdb;

		$expired_time = time() - $this->session_ttl;

		// Try cache first to avoid expensive queries
		$cache_key        = 'rsl_expired_sessions_' . gmdate( 'Y-m-d-H', $expired_time );
		$expired_sessions = wp_cache_get( $cache_key, 'rsl_sessions' );

		if ( $expired_sessions === false ) {
			// Find expired sessions
			$expired_sessions = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_value LIKE %s
                 AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(option_value, '\"expires_at\":', -1), ',', 1) AS UNSIGNED) < %d",
					'rsl_session_%',
					'%expires_at%',
					$expired_time
				)
			);

			// Cache results for 30 minutes (only cache successful queries)
			if ( $expired_sessions !== null && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $expired_sessions, 'rsl_sessions', 1800 );
			}
		}

		// Delete expired sessions
		foreach ( $expired_sessions as $option_name ) {
			delete_option( $option_name );
		}

		if ( ! empty( $expired_sessions ) ) {
			rsl_log( sprintf( 'Cleaned up %d expired sessions', count( $expired_sessions ) ), 'debug' );
		}
	}
}
