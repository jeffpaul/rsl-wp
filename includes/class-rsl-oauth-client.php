<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth Client Management
 *
 * Handles OAuth 2.0 client credentials for API authentication
 * per RSL Open Licensing Protocol requirements.
 */
class RSL_OAuth_Client {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Clean up expired tokens periodically
		add_action( 'wp_scheduled_delete', array( $this, 'cleanup_expired_tokens' ) );
	}

	/**
	 * Create new OAuth client
	 *
	 * @param string $client_name Human readable name
	 * @param array  $options Additional options
	 * @return array|WP_Error Client data or error
	 */
	public function create_client( $client_name, $options = array() ) {
		global $wpdb;

		$client_id          = $this->generate_client_id();
		$client_secret      = $this->generate_client_secret();
		$client_secret_hash = wp_hash_password( $client_secret );

		$table_name = $wpdb->prefix . 'rsl_oauth_clients';

		$result = $wpdb->insert(
			$table_name,
			array(
				'client_id'          => $client_id,
				'client_secret_hash' => $client_secret_hash,
				'client_name'        => sanitize_text_field( $client_name ),
				'redirect_uris'      => isset( $options['redirect_uris'] ) ?
					implode( ',', (array) $options['redirect_uris'] ) : '',
				'grant_types'        => isset( $options['grant_types'] ) ?
					sanitize_text_field( $options['grant_types'] ) : 'client_credentials',
				'active'             => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		// Clear client cache after successful insert
		if ( $result && ! $wpdb->last_error ) {
			wp_cache_delete( 'rsl_oauth_clients', 'rsl_oauth' );
			wp_cache_delete( 'rsl_oauth_client_' . $client_id, 'rsl_oauth' );
		}

		if ( ! $result ) {
			return new WP_Error( 'client_creation_failed', 'Failed to create OAuth client: ' . $wpdb->last_error );
		}

		return array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret, // Only returned once during creation
			'client_name'   => $client_name,
			'active'        => true,
		);
	}

	/**
	 * Validate OAuth client credentials
	 *
	 * @param string $client_id
	 * @param string $client_secret
	 * @return bool|WP_Error True if valid, error otherwise
	 */
	public function validate_client( $client_id, $client_secret ) {
		global $wpdb;

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'invalid_client', 'Missing client credentials' );
		}

		$table_name = $wpdb->prefix . 'rsl_oauth_clients';

		// Try cache first
		$cache_key = 'rsl_oauth_client_validate_' . $client_id;
		$client    = wp_cache_get( $cache_key, 'rsl_oauth' );

		if ( $client === false ) {
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT client_secret_hash, active FROM `{$wpdb->prefix}rsl_oauth_clients` WHERE client_id = %s",
					$client_id
				)
			);

			// Cache successful results for 30 minutes
			if ( $client && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $client, 'rsl_oauth', 1800 );
			}
		}

		if ( ! $client ) {
			return new WP_Error( 'invalid_client', 'Client not found' );
		}

		if ( ! $client->active ) {
			return new WP_Error( 'invalid_client', 'Client is inactive' );
		}

		if ( ! wp_check_password( $client_secret, $client->client_secret_hash ) ) {
			return new WP_Error( 'invalid_client', 'Invalid client credentials' );
		}

		return true;
	}

	/**
	 * Get client by ID
	 *
	 * @param string $client_id
	 * @return array|null
	 */
	public function get_client( $client_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_oauth_clients';

		// Try cache first
		$cache_key = 'rsl_oauth_client_' . $client_id;
		$client    = wp_cache_get( $cache_key, 'rsl_oauth' );

		if ( $client === false ) {
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, client_id, client_name, redirect_uris, grant_types, active, created_at
                 FROM `{$wpdb->prefix}rsl_oauth_clients` WHERE client_id = %s",
					$client_id
				),
				ARRAY_A
			);

			// Cache successful results for 30 minutes
			if ( $client && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $client, 'rsl_oauth', 1800 );
			}
		}

		if ( ! $client ) {
			return null;
		}

		return $client;
	}

	/**
	 * List all clients
	 *
	 * @param array $args Query arguments
	 * @return array
	 */
	public function list_clients( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_oauth_clients';
		$where      = '';
		$params     = array();

		if ( isset( $args['active'] ) ) {
			$where    = ' WHERE active = %d';
			$params[] = $args['active'];
		}

		$base_query = "SELECT id, client_id, client_name, grant_types, active, created_at 
                       FROM `{$wpdb->prefix}rsl_oauth_clients`" . $where . ' ORDER BY created_at DESC';

		// Try cache first
		$cache_key = 'rsl_oauth_clients_' . md5( serialize( $args ) );
		$clients   = wp_cache_get( $cache_key, 'rsl_oauth' );

		if ( $clients === false ) {
			if ( ! empty( $params ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$clients = $wpdb->get_results( $wpdb->prepare( $base_query, ...$params ), ARRAY_A );
			} else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$clients = $wpdb->get_results( $base_query, ARRAY_A );
			}

			// Cache successful results for 30 minutes
			if ( $clients !== null && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $clients, 'rsl_oauth', 1800 );
			}
		}

		return $clients ? $clients : array();
	}

	/**
	 * Revoke client (deactivate)
	 *
	 * @param string $client_id
	 * @return bool
	 */
	public function revoke_client( $client_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_oauth_clients';

		$result = $wpdb->update(
			$table_name,
			array( 'active' => 0 ),
			array( 'client_id' => $client_id ),
			array( '%d' ),
			array( '%s' )
		);

		// Drop cached credentials/client data so the revocation takes effect immediately
		wp_cache_delete( 'rsl_oauth_client_validate_' . $client_id, 'rsl_oauth' );
		wp_cache_delete( 'rsl_oauth_client_' . $client_id, 'rsl_oauth' );
		wp_cache_delete( 'rsl_oauth_clients', 'rsl_oauth' );

		return $result !== false;
	}

	/**
	 * Store token for revocation tracking
	 *
	 * @param string $jti JWT ID
	 * @param string $client_id
	 * @param int    $license_id
	 * @param int    $expires_at Unix timestamp
	 * @param array  $metadata Additional metadata
	 * @return bool
	 */
	public function store_token( $jti, $client_id, $license_id, $expires_at, $metadata = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		$result = $wpdb->insert(
			$table_name,
			array(
				'jti'             => $jti,
				'client_id'       => $client_id,
				'license_id'      => $license_id,
				'order_id'        => isset( $metadata['order_id'] ) ? $metadata['order_id'] : null,
				'subscription_id' => isset( $metadata['subscription_id'] ) ? $metadata['subscription_id'] : null,
				'expires_at'      => gmdate( 'Y-m-d H:i:s', $expires_at ),
				'revoked'         => 0,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
		);

		// Clear token cache after successful insert
		if ( $result && ! $wpdb->last_error ) {
			wp_cache_delete( 'rsl_token_' . $jti, 'rsl_tokens' );
		}

		return $result !== false;
	}

	/**
	 * Check if token is revoked
	 *
	 * @param string $jti JWT ID
	 * @return bool
	 */
	public function is_token_revoked( $jti ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		// Try cache first
		$cache_key = 'rsl_token_' . $jti;
		$revoked   = wp_cache_get( $cache_key, 'rsl_tokens' );

		if ( $revoked === false ) {
			$revoked = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT revoked FROM `{$wpdb->prefix}rsl_tokens` WHERE jti = %s",
					$jti
				)
			);

			// Cache successful results for 15 minutes (shorter TTL for token status)
			if ( $revoked !== null && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $revoked, 'rsl_tokens', 900 );
			}
		}

		return (bool) $revoked;
	}

	/**
	 * Revoke token
	 *
	 * @param string $jti JWT ID
	 * @return bool
	 */
	public function revoke_token( $jti ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		$result = $wpdb->update(
			$table_name,
			array( 'revoked' => 1 ),
			array( 'jti' => $jti ),
			array( '%d' ),
			array( '%s' )
		);

		wp_cache_delete( 'rsl_token_' . $jti, 'rsl_tokens' );

		return $result !== false;
	}

	/**
	 * Revoke all tokens for an order (refund scenario)
	 *
	 * @param int $order_id WooCommerce order ID
	 * @return int Number of tokens revoked
	 */
	public function revoke_tokens_for_order( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		$jtis = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT jti FROM `{$wpdb->prefix}rsl_tokens` WHERE order_id = %d AND revoked = 0",
				$order_id
			)
		);

		$result = $wpdb->update(
			$table_name,
			array( 'revoked' => 1 ),
			array(
				'order_id' => $order_id,
				'revoked'  => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		$this->clear_token_cache( $jtis );

		return $result ? $result : 0;
	}

	/**
	 * Revoke all tokens for a subscription (cancellation scenario)
	 *
	 * @param int $subscription_id WooCommerce subscription ID
	 * @return int Number of tokens revoked
	 */
	public function revoke_tokens_for_subscription( $subscription_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		$jtis = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT jti FROM `{$wpdb->prefix}rsl_tokens` WHERE subscription_id = %d AND revoked = 0",
				$subscription_id
			)
		);

		$result = $wpdb->update(
			$table_name,
			array( 'revoked' => 1 ),
			array(
				'subscription_id' => $subscription_id,
				'revoked'         => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		$this->clear_token_cache( $jtis );

		return $result ? $result : 0;
	}

	/**
	 * Clear cached revocation status for the given token IDs
	 *
	 * @param array $jtis JWT IDs
	 */
	private function clear_token_cache( $jtis ) {
		foreach ( (array) $jtis as $jti ) {
			wp_cache_delete( 'rsl_token_' . $jti, 'rsl_tokens' );
		}
	}

	/**
	 * Clean up expired tokens
	 */
	public function cleanup_expired_tokens() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_tokens';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}rsl_tokens` WHERE expires_at < %s",
				current_time( 'mysql', true )
			)
		);

		// Clear token cache after cleanup
		if ( $deleted > 0 ) {
			wp_cache_flush_group( 'rsl_tokens' );
		}

		if ( $deleted > 0 ) {
			rsl_log( sprintf( 'RSL OAuth: Cleaned up %d expired tokens', $deleted ) );
		}
	}

	/**
	 * Parse Authorization header for Basic auth
	 *
	 * @param string $auth_header
	 * @return array|null [client_id, client_secret] or null
	 */
	public function parse_basic_auth( $auth_header ) {
		if ( empty( $auth_header ) || strpos( $auth_header, 'Basic ' ) !== 0 ) {
			return null;
		}

		$encoded = substr( $auth_header, 6 );
		$decoded = base64_decode( $encoded );

		if ( ! $decoded || strpos( $decoded, ':' ) === false ) {
			return null;
		}

		return explode( ':', $decoded, 2 );
	}

	/**
	 * Generate unique client ID
	 *
	 * @return string
	 */
	private function generate_client_id() {
		return 'rsl_' . wp_generate_password( 16, false, false );
	}

	/**
	 * Generate secure client secret
	 *
	 * @return string
	 */
	private function generate_client_secret() {
		return wp_generate_password( 32, true, true );
	}

	/**
	 * Generate JWT ID (jti) for token uniqueness
	 *
	 * @return string
	 */
	public function generate_jti() {
		return wp_generate_uuid4();
	}
}
