<?php
/**
 * RSL License management class
 *
 * Handles CRUD operations for RSL licenses and provides
 * methods for license validation and retrieval.
 *
 * @package RSL_WP
 * @since 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RSL License management class.
 *
 * Manages license creation, retrieval, updating, and deletion
 * operations for the RSL WordPress plugin.
 */
class RSL_License {

	/**
	 * Database table name for storing licenses.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Writable license columns mapped to their $wpdb format placeholders.
	 *
	 * Only these keys are ever passed to $wpdb->insert() / $wpdb->update(),
	 * since wpdb does not escape column names.
	 *
	 * @var array
	 */
	private static $column_formats = array(
		'name'             => '%s',
		'description'      => '%s',
		'content_url'      => '%s',
		'server_url'       => '%s',
		'encrypted'        => '%d',
		'lastmod'          => '%s',
		'permits_usage'    => '%s',
		'permits_user'     => '%s',
		'permits_geo'      => '%s',
		'prohibits_usage'  => '%s',
		'prohibits_user'   => '%s',
		'prohibits_geo'    => '%s',
		'payment_type'     => '%s',
		'standard_url'     => '%s',
		'custom_url'       => '%s',
		'amount'           => '%f',
		'currency'         => '%s',
		'warranty'         => '%s',
		'disclaimer'       => '%s',
		'schema_url'       => '%s',
		'copyright_holder' => '%s',
		'copyright_type'   => '%s',
		'contact_email'    => '%s',
		'contact_url'      => '%s',
		'terms_url'        => '%s',
		'active'           => '%d',
		'updated_at'       => '%s',
	);

	/**
	 * Constructor.
	 *
	 * Initializes the license class and sets up database table name.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'rsl_licenses';
	}

	/**
	 * Create a new license.
	 *
	 * @param array $data License data array containing fields like name, content_url, etc.
	 * @return int|false License ID on success, false on failure.
	 */
	public function create_license( $data ) {
		global $wpdb;

		$defaults = array(
			'name'             => '',
			'description'      => '',
			'content_url'      => '',
			'server_url'       => '',
			'encrypted'        => 0,
			'lastmod'          => current_time( 'mysql' ),
			'permits_usage'    => '',
			'permits_user'     => '',
			'permits_geo'      => '',
			'prohibits_usage'  => '',
			'prohibits_user'   => '',
			'prohibits_geo'    => '',
			'payment_type'     => 'free',
			'standard_url'     => '',
			'custom_url'       => '',
			'amount'           => 0,
			'currency'         => 'USD',
			'warranty'         => '',
			'disclaimer'       => '',
			'schema_url'       => '',
			'copyright_holder' => '',
			'copyright_type'   => '',
			'contact_email'    => '',
			'contact_url'      => '',
			'terms_url'        => '',
			'active'           => 1,
		);

		$data = $this->sanitize_license_data( wp_parse_args( $data, $defaults ) );
		unset( $data['updated_at'] );

		// Validate required fields.
		if ( empty( $data['name'] ) || empty( $data['content_url'] ) ) {
			// Error logged elsewhere if needed.
			return false;
		}

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			$this->get_formats( $data )
		);

		if ( false === $result ) {
			// Database error handled elsewhere.
			return false;
		}

		$this->invalidate_cache( $wpdb->insert_id );

		return $wpdb->insert_id;
	}

	/**
	 * Get a license by ID.
	 *
	 * @param int $id License ID.
	 * @return array|null License data array on success, null on failure.
	 */
	public function get_license( $id ) {
		global $wpdb;

		$id = intval( $id );
		if ( 0 >= $id ) {
			return null;
		}

		// Check cache first.
		$cache_key = "rsl_license_{$id}";
		$result    = wp_cache_get( $cache_key, 'rsl_licenses' );

		if ( false === $result ) {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}rsl_licenses` WHERE id = %d",
					$id
				),
				ARRAY_A
			);

			// Cache the result for 1 hour.
			if ( $result ) {
				wp_cache_set( $cache_key, $result, 'rsl_licenses', 3600 );
			}
		}

		if ( $wpdb->last_error ) {
			// Database error handled elsewhere.
			return null;
		}

		return $result;
	}

	/**
	 * Get multiple licenses with optional filtering.
	 *
	 * @param array $args Optional arguments for filtering and sorting.
	 * @return array|null Array of license data on success, null on failure.
	 */
	public function get_licenses( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active'  => 1,
			'limit'   => -1,
			'offset'  => 0,
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate and sanitize orderby to prevent SQL injection.
		$allowed_orderby = array( 'id', 'name', 'created_at', 'updated_at', 'lastmod', 'payment_type' );
		if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = 'name';
		}

		// Validate order parameter.
		$args['order'] = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		// Validate numeric parameters.
		$args['limit']  = max( -1, intval( $args['limit'] ) );
		$args['offset'] = max( 0, intval( $args['offset'] ) );

		$where  = array();
		$values = array();

		if ( null !== $args['active'] ) {
			$where[]  = 'active = %d';
			$values[] = intval( $args['active'] );
		}

		$base_sql = "SELECT * FROM `{$wpdb->prefix}rsl_licenses`";

		if ( ! empty( $where ) ) {
			$base_sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		// Safe to use directly since $args['orderby'] is validated against allowlist.
		$base_sql .= " ORDER BY `{$args['orderby']}` {$args['order']}";

		if ( 0 < $args['limit'] ) {
			$base_sql .= ' LIMIT %d';
			$values[]  = $args['limit'];

			if ( 0 < $args['offset'] ) {
				$base_sql .= ' OFFSET %d';
				$values[]  = $args['offset'];
			}
		}

		if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $base_sql, $values );
		} else {
			$sql = $base_sql;
		}

		// Check cache first for listings.
		$cache_key = 'rsl_licenses_' . md5( $sql . serialize( $values ) ) . ':' . wp_cache_get_last_changed( 'rsl_licenses' );
		$results   = wp_cache_get( $cache_key, 'rsl_licenses' );

		if ( false === $results ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $sql, ARRAY_A );

			// Cache the results for 30 minutes.
			if ( ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $results, 'rsl_licenses', 1800 );
			}
		}

		// Add error handling.
		if ( $wpdb->last_error ) {
			// License query error handled elsewhere.
			return array();
		}

		return $results ? $results : array();
	}

	/**
	 * Update an existing license.
	 *
	 * @param int   $id   License ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_license( $id, $data ) {
		global $wpdb;

		$id = intval( $id );
		if ( 0 >= $id ) {
			// Invalid license ID logged elsewhere.
			return false;
		}

		$data               = $this->sanitize_license_data( $data );
		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $id ),
			$this->get_formats( $data ),
			array( '%d' )
		);

		if ( false === $result ) {
			// Database error handled elsewhere.
			return false;
		}

		$this->invalidate_cache( $id );

		return false !== $result;
	}

	/**
	 * Delete a license.
	 *
	 * @param int $id License ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_license( $id ) {
		global $wpdb;

		$id = intval( $id );
		if ( 0 >= $id ) {
			// Invalid license ID logged elsewhere.
			return false;
		}

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			// Database error handled elsewhere.
			return false;
		}

		$this->invalidate_cache( $id );

		return false !== $result;
	}

	/**
	 * Sanitize license data for storage.
	 *
	 * Drops any key that is not a writable license column and applies the
	 * same sanitizers the admin license form uses, so every write path
	 * (admin AJAX, Abilities API, programmatic callers) stores clean data.
	 *
	 * @param array $data Raw license data.
	 * @return array Sanitized license data containing only known columns.
	 */
	public function sanitize_license_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$data = array_intersect_key( $data, self::$column_formats );

		$url_fields = array( 'server_url', 'standard_url', 'custom_url', 'schema_url', 'contact_url', 'terms_url' );

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = '';
			}

			switch ( $key ) {
				case 'description':
					$data[ $key ] = sanitize_textarea_field( $value );
					break;

				case 'content_url':
					$value = trim( (string) $value );
					// Server-relative paths/patterns (e.g. "/images/*") are stored as provided when they pass the admin allowlist.
					if ( 0 === strpos( $value, '/' ) && 0 !== strpos( $value, '//' ) ) {
						$data[ $key ] = preg_match( '#^/[A-Za-z0-9._~!\'()*+,;=:@/\-%]*\*?\$?$#', $value ) ? $value : '';
					} else {
						$data[ $key ] = esc_url_raw( $value );
					}
					break;

				case 'encrypted':
				case 'active':
					$data[ $key ] = empty( $value ) ? 0 : 1;
					break;

				case 'amount':
					$data[ $key ] = min( 999999.99, max( 0, floatval( $value ) ) );
					break;

				case 'currency':
					$value        = strtoupper( sanitize_text_field( $value ) );
					$data[ $key ] = preg_match( '/^[A-Z]{3}$/', $value ) ? $value : '';
					break;

				case 'payment_type':
					$value        = sanitize_text_field( $value );
					$data[ $key ] = array_key_exists( $value, $this->get_payment_options() ) ? $value : 'free';
					break;

				case 'contact_email':
					$data[ $key ] = sanitize_email( $value );
					break;

				default:
					$data[ $key ] = in_array( $key, $url_fields, true )
						? esc_url_raw( $value )
						: sanitize_text_field( $value );
					break;
			}
		}

		return $data;
	}

	/**
	 * Build the $wpdb format array matching the keys of a data array.
	 *
	 * @param array $data Data keyed by allowlisted column name.
	 * @return array Format placeholders in the same order as $data.
	 */
	private function get_formats( $data ) {
		$formats = array();
		foreach ( array_keys( $data ) as $column ) {
			$formats[] = self::$column_formats[ $column ];
		}
		return $formats;
	}

	/**
	 * Invalidate cached license data after a write.
	 *
	 * Deletes the single-license entry and invalidates all cached listings,
	 * so deactivated or repriced licenses take effect immediately even with
	 * a persistent object cache.
	 *
	 * @param int $id License ID.
	 */
	private function invalidate_cache( $id ) {
		$id = intval( $id );
		if ( 0 < $id ) {
			wp_cache_delete( "rsl_license_{$id}", 'rsl_licenses' );
		}
		wp_cache_delete( 'rsl_license_count', 'rsl_licenses' );

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'rsl_licenses' );
		}

		// Listing keys are salted with last_changed, so bumping it invalidates them on any cache backend.
		if ( function_exists( 'wp_cache_set_last_changed' ) ) {
			wp_cache_set_last_changed( 'rsl_licenses' );
		} else {
			wp_cache_delete( 'last_changed', 'rsl_licenses' );
		}
	}

	/**
	 * Generate RSL XML from license data.
	 *
	 * @param array $license_data License data array.
	 * @param array $options      Optional settings for XML generation.
	 * @return string Generated RSL XML string.
	 */
	public function generate_rsl_xml( $license_data, $options = array() ) {
		$defaults = array(
			'namespace'  => 'https://rslstandard.org/rsl',
			'standalone' => true,
		);

		$options = wp_parse_args( $options, $defaults );

		$xml = '';

		if ( $options['standalone'] ) {
			$xml .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		}

		$xml .= '<rsl xmlns="' . esc_attr( $options['namespace'] ) . '">' . "\n";
		$xml .= '  <content url="' . esc_attr( $license_data['content_url'] ) . '"';

		if ( ! empty( $license_data['server_url'] ) ) {
			$xml .= ' server="' . esc_attr( $license_data['server_url'] ) . '"';
		}

		if ( ! empty( $license_data['encrypted'] ) && 1 === $license_data['encrypted'] ) {
			$xml .= ' encrypted="true"';
		}

		if ( ! empty( $license_data['lastmod'] ) ) {
			$xml .= ' lastmod="' . esc_attr( gmdate( 'c', strtotime( $license_data['lastmod'] ) ) ) . '"';
		}

		$xml .= '>' . "\n";

		if ( ! empty( $license_data['schema_url'] ) ) {
			$xml .= '    <schema>' . esc_html( $license_data['schema_url'] ) . '</schema>' . "\n";
		}

		if ( ! empty( $license_data['copyright_holder'] ) ) {
			$xml .= '    <copyright';
			if ( ! empty( $license_data['copyright_type'] ) ) {
				$xml .= ' type="' . esc_attr( $license_data['copyright_type'] ) . '"';
			}
			if ( ! empty( $license_data['contact_email'] ) ) {
				$xml .= ' contactEmail="' . esc_attr( $license_data['contact_email'] ) . '"';
			}
			if ( ! empty( $license_data['contact_url'] ) ) {
				$xml .= ' contactUrl="' . esc_attr( $license_data['contact_url'] ) . '"';
			}
			$xml .= '>' . esc_html( $license_data['copyright_holder'] ) . '</copyright>' . "\n";
		}

		if ( ! empty( $license_data['terms_url'] ) ) {
			$xml .= '    <terms>' . esc_html( $license_data['terms_url'] ) . '</terms>' . "\n";
		}

		$xml .= '    <license>' . "\n";

		if ( ! empty( $license_data['permits_usage'] ) ) {
			$xml .= '      <permits type="usage">' . esc_html( $license_data['permits_usage'] ) . '</permits>' . "\n";
		}

		if ( ! empty( $license_data['permits_user'] ) ) {
			$xml .= '      <permits type="user">' . esc_html( $license_data['permits_user'] ) . '</permits>' . "\n";
		}

		if ( ! empty( $license_data['permits_geo'] ) ) {
			$xml .= '      <permits type="geo">' . esc_html( $license_data['permits_geo'] ) . '</permits>' . "\n";
		}

		if ( ! empty( $license_data['prohibits_usage'] ) ) {
			$xml .= '      <prohibits type="usage">' . esc_html( $license_data['prohibits_usage'] ) . '</prohibits>' . "\n";
		}

		if ( ! empty( $license_data['prohibits_user'] ) ) {
			$xml .= '      <prohibits type="user">' . esc_html( $license_data['prohibits_user'] ) . '</prohibits>' . "\n";
		}

		if ( ! empty( $license_data['prohibits_geo'] ) ) {
			$xml .= '      <prohibits type="geo">' . esc_html( $license_data['prohibits_geo'] ) . '</prohibits>' . "\n";
		}

		if ( ! empty( $license_data['payment_type'] ) && 'free' !== $license_data['payment_type'] ) {
			$xml .= '      <payment type="' . esc_attr( $license_data['payment_type'] ) . '">' . "\n";

			if ( ! empty( $license_data['standard_url'] ) ) {
				$xml .= '        <standard>' . esc_html( $license_data['standard_url'] ) . '</standard>' . "\n";
			}

			if ( ! empty( $license_data['custom_url'] ) ) {
				$xml .= '        <custom>' . esc_html( $license_data['custom_url'] ) . '</custom>' . "\n";
			}

			if ( ! empty( $license_data['amount'] ) && $license_data['amount'] > 0 ) {
				$xml .= '        <amount currency="' . esc_attr( $license_data['currency'] ) . '">' .
						esc_html( $license_data['amount'] ) . '</amount>' . "\n";
			}

			$xml .= '      </payment>' . "\n";
		} else {
			$xml .= '      <payment type="free"/>' . "\n";
		}

		if ( ! empty( $license_data['warranty'] ) ) {
			$xml .= '      <legal type="warranty">' . esc_html( $license_data['warranty'] ) . '</legal>' . "\n";
		}

		if ( ! empty( $license_data['disclaimer'] ) ) {
			$xml .= '      <legal type="disclaimer">' . esc_html( $license_data['disclaimer'] ) . '</legal>' . "\n";
		}

		$xml .= '    </license>' . "\n";
		$xml .= '  </content>' . "\n";
		$xml .= '</rsl>';

		return $xml;
	}

	/**
	 * Get available usage options for license configuration.
	 *
	 * @return array Array of usage options with labels.
	 */
	public function get_usage_options() {
		return array(
			'all'          => __( 'All automated processing', 'rsl-wp' ),
			'train-ai'     => __( 'Train AI model', 'rsl-wp' ),
			'train-genai'  => __( 'Train generative AI model', 'rsl-wp' ),
			'ai-use'       => __( 'Use as AI input (RAG)', 'rsl-wp' ),
			'ai-summarize' => __( 'AI summarization', 'rsl-wp' ),
			'search'       => __( 'Search indexing', 'rsl-wp' ),
		);
	}

	/**
	 * Get available user options for license configuration.
	 *
	 * @return array Array of user options with labels.
	 */
	public function get_user_options() {
		return array(
			'commercial'     => __( 'Commercial use', 'rsl-wp' ),
			'non-commercial' => __( 'Non-commercial use', 'rsl-wp' ),
			'education'      => __( 'Educational use', 'rsl-wp' ),
			'government'     => __( 'Government use', 'rsl-wp' ),
			'personal'       => __( 'Personal use', 'rsl-wp' ),
		);
	}

	/**
	 * Get available payment options for license configuration.
	 *
	 * @return array Array of payment options with labels.
	 */
	public function get_payment_options() {
		return array(
			'free'         => __( 'Free', 'rsl-wp' ),
			'purchase'     => __( 'One-time purchase', 'rsl-wp' ),
			'subscription' => __( 'Subscription', 'rsl-wp' ),
			'training'     => __( 'Per training use', 'rsl-wp' ),
			'crawl'        => __( 'Per crawl', 'rsl-wp' ),
			'inference'    => __( 'Per inference', 'rsl-wp' ),
			'attribution'  => __( 'Attribution required', 'rsl-wp' ),
			'royalty'      => __( 'Royalty', 'rsl-wp' ),
		);
	}

	/**
	 * Get available warranty options for license configuration.
	 *
	 * @return array Array of warranty options with labels.
	 */
	public function get_warranty_options() {
		return array(
			'ownership'       => __( 'Ownership rights', 'rsl-wp' ),
			'authority'       => __( 'Authorization to license', 'rsl-wp' ),
			'no-infringement' => __( 'No third-party infringement', 'rsl-wp' ),
			'privacy-consent' => __( 'Privacy consents obtained', 'rsl-wp' ),
			'no-malware'      => __( 'Free from malware', 'rsl-wp' ),
		);
	}

	/**
	 * Get available disclaimer options for license configuration.
	 *
	 * @return array Array of disclaimer options with labels.
	 */
	public function get_disclaimer_options() {
		return array(
			'as-is'        => __( 'Provided "as is"', 'rsl-wp' ),
			'no-warranty'  => __( 'No warranties', 'rsl-wp' ),
			'no-liability' => __( 'No liability', 'rsl-wp' ),
			'no-indemnity' => __( 'No indemnification', 'rsl-wp' ),
		);
	}
}
