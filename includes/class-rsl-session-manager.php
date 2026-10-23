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
		$session_id = $this->generate_session_id();
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
			'options'        => $this->sanitize_options( $options ),
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
	 * Generate a cryptographically secure session ID formatted as a UUIDv4
	 *
	 * @return string
	 */
	private function generate_session_id() {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 ); // Version 4.
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 ); // RFC 4122 variant.

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}

	/**
	 * Reduce client-supplied session options to a known allowlist
	 *
	 * Session creation is unauthenticated, so arbitrary request parameters
	 * must never be persisted as-is.
	 *
	 * @param array $options Raw options (typically request params).
	 * @return array Sanitized options.
	 */
	private function sanitize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$sanitized = array();

		if ( isset( $options['license_id'] ) && is_scalar( $options['license_id'] ) ) {
			$sanitized['license_id'] = absint( $options['license_id'] );
		}

		if ( isset( $options['client'] ) && is_scalar( $options['client'] ) ) {
			$sanitized['client'] = substr( sanitize_text_field( (string) $options['client'] ), 0, 255 );
		}

		return $sanitized;
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

		$now = time();

		// Session options are PHP-serialized, so expiry must be checked in PHP
		// rather than parsed out of option_value in SQL. Batch-limit the scan.
		$batch_size   = (int) apply_filters( 'rsl_session_cleanup_batch_size', 500 );
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
				$wpdb->esc_like( 'rsl_session_' ) . '%',
				max( 1, $batch_size )
			)
		);

		$expired_sessions = array();
		foreach ( (array) $option_names as $option_name ) {
			$session = get_option( $option_name );

			if ( ! is_array( $session ) || ! isset( $session['expires_at'] ) || (int) $session['expires_at'] < $now ) {
				delete_option( $option_name );
				$expired_sessions[] = $option_name;
			}
		}

		if ( ! empty( $expired_sessions ) ) {
			rsl_log( sprintf( 'Cleaned up %d expired sessions', count( $expired_sessions ) ), 'debug' );
		}
	}
}
