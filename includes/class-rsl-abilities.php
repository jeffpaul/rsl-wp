<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSL_Abilities {

	private $license_handler;
	private $server;

	public function __construct() {
		$this->license_handler = new RSL_License();
		$this->server          = new RSL_Server();

		add_action( 'abilities_api_init', array( $this, 'register_abilities' ) );
	}

	public function register_abilities() {
		$this->register_admin_abilities();
		$this->register_public_abilities();
		$this->register_server_abilities();
	}

	private function register_admin_abilities() {
		// License Management
		wp_register_ability(
			'rsl-licensing/create-license',
			array(
				'label'               => __( 'Create RSL License', 'rsl-wp' ),
				'description'         => __( 'Creates a new Really Simple Licensing (RSL) license with specified terms, permissions, and payment configuration. Supports all RSL 1.0 specification elements including usage restrictions, geographic limitations, and payment types.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'             => array(
							'type'        => 'string',
							'description' => 'Human-readable name for the license',
							'minLength'   => 1,
						),
						'description'      => array(
							'type'        => 'string',
							'description' => 'Detailed description of the license terms',
						),
						'content_url'      => array(
							'type'        => 'string',
							'description' => 'URL pattern this license applies to (supports wildcards)',
							'minLength'   => 1,
						),
						'payment_type'     => array(
							'type'        => 'string',
							'enum'        => array( 'free', 'purchase', 'subscription', 'training', 'crawl', 'inference', 'attribution' ),
							'default'     => 'free',
							'description' => 'Type of payment or compensation required',
						),
						'amount'           => array(
							'type'        => 'number',
							'minimum'     => 0,
							'description' => 'License fee amount (if applicable)',
						),
						'currency'         => array(
							'type'        => 'string',
							'pattern'     => '^[A-Z]{3}$',
							'default'     => 'USD',
							'description' => 'Currency code (ISO 4217)',
						),
						'permits_usage'    => array(
							'type'        => 'string',
							'description' => 'Comma-separated usage types allowed (all, train-ai, search, etc.)',
						),
						'prohibits_usage'  => array(
							'type'        => 'string',
							'description' => 'Comma-separated usage types prohibited',
						),
						'permits_user'     => array(
							'type'        => 'string',
							'description' => 'User types allowed (commercial, non-commercial, education, etc.)',
						),
						'prohibits_user'   => array(
							'type'        => 'string',
							'description' => 'User types prohibited',
						),
						'permits_geo'      => array(
							'type'        => 'string',
							'description' => 'Geographic regions allowed (ISO 3166-1 alpha-2 codes)',
						),
						'prohibits_geo'    => array(
							'type'        => 'string',
							'description' => 'Geographic regions prohibited',
						),
						'copyright_holder' => array(
							'type'        => 'string',
							'description' => 'Name of copyright holder',
						),
						'contact_email'    => array(
							'type'        => 'string',
							'format'      => 'email',
							'description' => 'Contact email for licensing inquiries',
						),
						'server_url'       => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Optional RSL License Server URL for authentication',
						),
					),
					'required'             => array( 'name', 'content_url' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'license_id' => array(
							'type'        => 'integer',
							'description' => 'Unique ID of the created license',
						),
						'success'    => array(
							'type'        => 'boolean',
							'description' => 'Whether the license was successfully created',
						),
						'xml_url'    => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'URL to access the license XML',
						),
					),
				),
				'execute_callback'    => array( $this, 'create_license' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'rsl-licensing/update-license',
			array(
				'label'               => __( 'Update RSL License', 'rsl-wp' ),
				'description'         => __( 'Updates an existing RSL license configuration. Modifies license terms, permissions, payment settings, and other properties while maintaining license history and validation.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'license_id'       => array(
							'type'        => 'integer',
							'description' => 'ID of the license to update',
							'minimum'     => 1,
						),
						'name'             => array(
							'type'        => 'string',
							'description' => 'Updated license name',
						),
						'description'      => array(
							'type'        => 'string',
							'description' => 'Updated license description',
						),
						'payment_type'     => array(
							'type'        => 'string',
							'enum'        => array( 'free', 'purchase', 'subscription', 'training', 'crawl', 'inference', 'attribution' ),
							'description' => 'Updated payment type',
						),
						'amount'           => array(
							'type'        => 'number',
							'minimum'     => 0,
							'description' => 'Updated license fee',
						),
						'active'           => array(
							'type'        => 'boolean',
							'description' => 'Whether the license is active',
						),
						'content_url'      => array(
							'type'        => 'string',
							'description' => 'Updated URL pattern this license applies to (supports wildcards)',
							'minLength'   => 1,
						),
						'currency'         => array(
							'type'        => 'string',
							'pattern'     => '^[A-Z]{3}$',
							'description' => 'Updated currency code (ISO 4217)',
						),
						'permits_usage'    => array(
							'type'        => 'string',
							'description' => 'Comma-separated usage types allowed (all, train-ai, search, etc.)',
						),
						'prohibits_usage'  => array(
							'type'        => 'string',
							'description' => 'Comma-separated usage types prohibited',
						),
						'permits_user'     => array(
							'type'        => 'string',
							'description' => 'User types allowed (commercial, non-commercial, education, etc.)',
						),
						'prohibits_user'   => array(
							'type'        => 'string',
							'description' => 'User types prohibited',
						),
						'permits_geo'      => array(
							'type'        => 'string',
							'description' => 'Geographic regions allowed (ISO 3166-1 alpha-2 codes)',
						),
						'prohibits_geo'    => array(
							'type'        => 'string',
							'description' => 'Geographic regions prohibited',
						),
						'copyright_holder' => array(
							'type'        => 'string',
							'description' => 'Name of copyright holder',
						),
						'contact_email'    => array(
							'type'        => 'string',
							'format'      => 'email',
							'description' => 'Contact email for licensing inquiries',
						),
						'server_url'       => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Optional RSL License Server URL for authentication',
						),
					),
					'required'             => array( 'license_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array(
							'type'        => 'boolean',
							'description' => 'Whether the update was successful',
						),
						'updated_fields' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'List of fields that were updated',
						),
					),
				),
				'execute_callback'    => array( $this, 'update_license' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'rsl-licensing/delete-license',
			array(
				'label'               => __( 'Delete RSL License', 'rsl-wp' ),
				'description'         => __( 'Permanently removes an RSL license from the system. This action cannot be undone and will affect any content currently using this license.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'license_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the license to delete',
							'minimum'     => 1,
						),
						'confirm'    => array(
							'type'        => 'boolean',
							'description' => 'Confirmation that deletion is intended',
							'const'       => true,
						),
					),
					'required'             => array( 'license_id', 'confirm' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array(
							'type'        => 'boolean',
							'description' => 'Whether the license was successfully deleted',
						),
						'license_name' => array(
							'type'        => 'string',
							'description' => 'Name of the deleted license for confirmation',
						),
					),
				),
				'execute_callback'    => array( $this, 'delete_license' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'rsl-licensing/list-licenses',
			array(
				'label'               => __( 'List RSL Licenses', 'rsl-wp' ),
				'description'         => __( 'Retrieves all RSL licenses with optional filtering by status, payment type, or search terms. Returns comprehensive license data for administration and management.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'active_only'  => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Return only active licenses',
						),
						'payment_type' => array(
							'type'        => 'string',
							'enum'        => array( 'free', 'purchase', 'subscription', 'training', 'crawl', 'inference', 'attribution' ),
							'description' => 'Filter by payment type',
						),
						'search'       => array(
							'type'        => 'string',
							'description' => 'Search term to filter license names/descriptions',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'licenses' => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'           => array( 'type' => 'integer' ),
									'name'         => array( 'type' => 'string' ),
									'payment_type' => array( 'type' => 'string' ),
									'active'       => array( 'type' => 'boolean' ),
									'created_at'   => array(
										'type'   => 'string',
										'format' => 'date-time',
									),
								),
							),
							'description' => 'Array of license objects',
						),
						'total'    => array(
							'type'        => 'integer',
							'description' => 'Total number of licenses matching criteria',
						),
					),
				),
				'execute_callback'    => array( $this, 'list_licenses' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'rsl-licensing/update-settings',
			array(
				'label'               => __( 'Update RSL Settings', 'rsl-wp' ),
				'description'         => __( 'Configures global RSL plugin settings including default license, integration methods (HTML injection, HTTP headers, robots.txt), and namespace configuration.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'global_license_id'     => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'ID of license to use site-wide (0 for none)',
						),
						'enable_html_injection' => array(
							'type'        => 'boolean',
							'description' => 'Embed RSL licenses in HTML head',
						),
						'enable_http_headers'   => array(
							'type'        => 'boolean',
							'description' => 'Add RSL Link headers to HTTP responses',
						),
						'enable_robots_txt'     => array(
							'type'        => 'boolean',
							'description' => 'Include RSL directives in robots.txt',
						),
						'enable_rss_feed'       => array(
							'type'        => 'boolean',
							'description' => 'Add RSL licensing to RSS feeds',
						),
						'enable_media_metadata' => array(
							'type'        => 'boolean',
							'description' => 'Embed RSL licenses in media file metadata',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'          => array(
							'type'        => 'boolean',
							'description' => 'Whether settings were successfully updated',
						),
						'updated_settings' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'List of settings that were changed',
						),
					),
				),
				'execute_callback'    => array( $this, 'update_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	private function register_public_abilities() {
		wp_register_ability(
			'rsl-licensing/get-content-license',
			array(
				'label'               => __( 'Get Content License', 'rsl-wp' ),
				'description'         => __( 'Retrieves RSL license information for specific content URL. Returns applicable license terms, permissions, payment requirements, and XML data for automated systems and AI agents.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'content_url' => array(
							'type'        => 'string',
							'description' => 'URL or path to check for license coverage',
							'minLength'   => 1,
						),
						'format'      => array(
							'type'        => 'string',
							'enum'        => array( 'json', 'xml' ),
							'default'     => 'json',
							'description' => 'Response format (JSON metadata or XML)',
						),
					),
					'required'             => array( 'content_url' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'has_license' => array(
							'type'        => 'boolean',
							'description' => 'Whether content has applicable license',
						),
						'license'     => array(
							'type'        => 'object',
							'properties'  => array(
								'name'            => array( 'type' => 'string' ),
								'payment_type'    => array( 'type' => 'string' ),
								'permits_usage'   => array( 'type' => 'string' ),
								'prohibits_usage' => array( 'type' => 'string' ),
								'xml_url'         => array(
									'type'   => 'string',
									'format' => 'uri',
								),
							),
							'description' => 'License details if applicable',
						),
					),
				),
				'execute_callback'    => array( $this, 'get_content_license' ),
				'permission_callback' => '__return_true',
			)
		);

		wp_register_ability(
			'rsl-licensing/validate-content',
			array(
				'label'               => __( 'Validate Content Licensing', 'rsl-wp' ),
				'description'         => __( 'Validates whether content usage complies with RSL licensing terms. Checks usage type, user category, and geographic restrictions against license permissions.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'content_url'  => array(
							'type'        => 'string',
							'description' => 'URL of content to validate',
							'minLength'   => 1,
						),
						'usage_type'   => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'train-ai', 'train-genai', 'ai-use', 'ai-summarize', 'search' ),
							'description' => 'Intended usage of the content',
						),
						'user_type'    => array(
							'type'        => 'string',
							'enum'        => array( 'commercial', 'non-commercial', 'education', 'government', 'personal' ),
							'description' => 'Category of user requesting access',
						),
						'geo_location' => array(
							'type'        => 'string',
							'pattern'     => '^[A-Z]{2}$',
							'description' => 'ISO 3166-1 alpha-2 country code',
						),
					),
					'required'             => array( 'content_url' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'valid'            => array(
							'type'        => 'boolean',
							'description' => 'Whether the requested usage is permitted',
						),
						'license_required' => array(
							'type'        => 'boolean',
							'description' => 'Whether payment/licensing is required',
						),
						'restrictions'     => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'List of applicable restrictions',
						),
						'license_url'      => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'URL for license acquisition if needed',
						),
					),
				),
				'execute_callback'    => array( $this, 'validate_content' ),
				'permission_callback' => '__return_true',
			)
		);

		wp_register_ability(
			'rsl-licensing/get-license-xml',
			array(
				'label'               => __( 'Get License XML', 'rsl-wp' ),
				'description'         => __( 'Generates RSL 1.0 compliant XML for a specific license. Returns machine-readable licensing data suitable for automated systems, crawlers, and AI agents.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'license_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the license to generate XML for',
							'minimum'     => 1,
						),
					),
					'required'             => array( 'license_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'xml'          => array(
							'type'        => 'string',
							'description' => 'RSL XML content',
						),
						'license_name' => array(
							'type'        => 'string',
							'description' => 'Human-readable license name',
						),
						'xml_url'      => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Direct URL to access this XML',
						),
					),
				),
				'execute_callback'    => array( $this, 'get_license_xml' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function register_server_abilities() {
		wp_register_ability(
			'rsl-licensing/issue-token',
			array(
				'label'               => __( 'Issue License Token', 'rsl-wp' ),
				'description'         => __( 'Issues authentication tokens for paid RSL licenses via Open Licensing Protocol (OLP). Handles payment verification and creates JWT tokens for authorized content access.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'license_id'      => array(
							'type'        => 'integer',
							'description' => 'ID of the license to issue token for',
							'minimum'     => 1,
						),
						'client'          => array(
							'type'        => 'string',
							'description' => 'Client identifier requesting the token',
							'minLength'   => 1,
						),
						'create_checkout' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Whether to create WooCommerce checkout for payment',
						),
						'wc_order_key'    => array(
							'type'        => 'string',
							'description' => 'WooCommerce order key for payment verification',
						),
					),
					'required'             => array( 'license_id', 'client' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'access_token' => array(
							'type'        => 'string',
							'description' => 'JWT access token for content access',
						),
						'token_type'   => array(
							'type'        => 'string',
							'const'       => 'Bearer',
							'description' => 'Token type (always Bearer)',
						),
						'expires_in'   => array(
							'type'        => 'integer',
							'description' => 'Token expiration time in seconds',
						),
						'checkout_url' => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Payment checkout URL if payment required',
						),
					),
				),
				'execute_callback'    => array( $this, 'issue_token' ),
				'permission_callback' => '__return_true',
			)
		);

		wp_register_ability(
			'rsl-licensing/introspect-token',
			array(
				'label'               => __( 'Introspect License Token', 'rsl-wp' ),
				'description'         => __( 'Validates and introspects RSL license tokens per RFC 7662. Verifies token authenticity, expiration, and associated permissions for secure content access control.', 'rsl-wp' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'token' => array(
							'type'        => 'string',
							'description' => 'JWT token to validate',
							'minLength'   => 1,
						),
					),
					'required'             => array( 'token' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'active'     => array(
							'type'        => 'boolean',
							'description' => 'Whether the token is valid and active',
						),
						'client_id'  => array(
							'type'        => 'string',
							'description' => 'Client identifier associated with token',
						),
						'license_id' => array(
							'type'        => 'integer',
							'description' => 'License ID this token grants access to',
						),
						'exp'        => array(
							'type'        => 'integer',
							'description' => 'Token expiration timestamp',
						),
						'scope'      => array(
							'type'        => 'string',
							'description' => 'Token scope and permissions',
						),
					),
				),
				'execute_callback'    => array( $this, 'introspect_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// === Execution Methods ===

	public function create_license( $input ) {
		$license_id = $this->license_handler->create_license( $input );

		if ( ! $license_id ) {
			return new \WP_Error( 'creation_failed', __( 'Failed to create license', 'rsl-wp' ) );
		}

		return array(
			'license_id' => $license_id,
			'success'    => true,
			'xml_url'    => home_url( "/?rsl_license={$license_id}" ),
		);
	}

	public function update_license( $input ) {
		$license_id = $input['license_id'];
		unset( $input['license_id'] );

		// Restrict to known license columns and sanitize, regardless of schema enforcement.
		$data = $this->license_handler->sanitize_license_data( $input );

		$result = $this->license_handler->update_license( $license_id, $data );

		if ( ! $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update license', 'rsl-wp' ) );
		}

		return array(
			'success'        => true,
			'updated_fields' => array_keys( $data ),
		);
	}

	public function delete_license( $input ) {
		$license = $this->license_handler->get_license( $input['license_id'] );

		if ( ! $license ) {
			return new \WP_Error( 'not_found', __( 'License not found', 'rsl-wp' ) );
		}

		$result = $this->license_handler->delete_license( $input['license_id'] );

		if ( ! $result ) {
			return new \WP_Error( 'deletion_failed', __( 'Failed to delete license', 'rsl-wp' ) );
		}

		return array(
			'success'      => true,
			'license_name' => $license['name'],
		);
	}

	public function list_licenses( $input ) {
		$args = array();

		if ( ! empty( $input['active_only'] ) ) {
			$args['active'] = 1;
		}

		if ( ! empty( $input['payment_type'] ) ) {
			$args['payment_type'] = $input['payment_type'];
		}

		$licenses = $this->license_handler->get_licenses( $args );

		if ( ! empty( $input['search'] ) ) {
			$search   = strtolower( $input['search'] );
			$licenses = array_filter(
				$licenses,
				function ( $license ) use ( $search ) {
					return strpos( strtolower( $license['name'] ), $search ) !== false ||
						strpos( strtolower( $license['description'] ), $search ) !== false;
				}
			);
		}

		return array(
			'licenses' => array_map(
				function ( $license ) {
					return array(
						'id'           => intval( $license['id'] ),
						'name'         => $license['name'],
						'payment_type' => $license['payment_type'],
						'active'       => (bool) $license['active'],
						'created_at'   => $license['created_at'],
					);
				},
				$licenses
			),
			'total'    => count( $licenses ),
		);
	}

	public function update_settings( $input ) {
		$updated = array();

		foreach ( $input as $key => $value ) {
			$option_key = 'rsl_' . $key;

			if ( update_option( $option_key, $value ) ) {
				$updated[] = $key;
			}
		}

		return array(
			'success'          => ! empty( $updated ),
			'updated_settings' => $updated,
		);
	}

	public function get_content_license( $input ) {
		// Use existing REST validation logic
		$request = new \WP_REST_Request( 'POST' );
		$request->set_body_params( array( 'content_url' => $input['content_url'] ) );

		$response = $this->server->rest_validate_license( $request );

		if ( is_wp_error( $response ) ) {
			// Only "no matching license" means the content is unlicensed; any other failure is an error.
			if ( 'no_license' !== $response->get_error_code() ) {
				return $response;
			}

			return array(
				'has_license' => false,
				'license'     => null,
			);
		}

		$data    = $response->get_data();
		$license = ! empty( $data['licenses'] ) ? $data['licenses'][0] : null;

		return array(
			'has_license' => $data['valid'],
			'license'     => $license ? array(
				'name'            => $license['name'],
				'payment_type'    => $license['payment_type'],
				'permits_usage'   => $license['permits_usage'],
				'prohibits_usage' => $license['prohibits_usage'],
				'xml_url'         => $license['xml_url'],
			) : null,
		);
	}

	public function validate_content( $input ) {
		$content_license = $this->get_content_license( array( 'content_url' => $input['content_url'] ) );

		// Fail closed: a failed lookup must not report the content as unrestricted.
		if ( is_wp_error( $content_license ) ) {
			return $content_license;
		}

		if ( ! $content_license['has_license'] ) {
			return array(
				'valid'            => true, // No license means no restrictions
				'license_required' => false,
				'restrictions'     => array(),
				'license_url'      => null,
			);
		}

		$license      = $content_license['license'];
		$restrictions = array();
		$valid        = true;

		// Check usage restrictions
		if ( ! empty( $input['usage_type'] ) && ! empty( $license['prohibits_usage'] ) ) {
			$prohibited = explode( ',', $license['prohibits_usage'] );
			if ( in_array( $input['usage_type'], $prohibited ) || in_array( 'all', $prohibited ) ) {
				$valid          = false;
				$restrictions[] = "Usage type '{$input['usage_type']}' is prohibited";
			}
		}

		return array(
			'valid'            => $valid,
			'license_required' => $license['payment_type'] !== 'free',
			'restrictions'     => $restrictions,
			'license_url'      => $valid ? null : $license['xml_url'],
		);
	}

	public function get_license_xml( $input ) {
		$license = $this->license_handler->get_license( $input['license_id'] );

		if ( ! $license || ! $license['active'] ) {
			return new \WP_Error( 'not_found', __( 'License not found or inactive', 'rsl-wp' ) );
		}

		$xml = $this->license_handler->generate_rsl_xml( $license );

		return array(
			'xml'          => $xml,
			'license_name' => $license['name'],
			'xml_url'      => home_url( "/?rsl_license={$license['id']}" ),
		);
	}

	public function issue_token( $input ) {
		// Delegate to existing OLP implementation
		$request = new \WP_REST_Request( 'POST' );
		$request->set_body_params( $input );

		$response = $this->server->olp_issue_token( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
	}

	public function introspect_token( $input ) {
		// Delegate to existing OLP implementation
		$request = new \WP_REST_Request( 'POST' );
		$request->set_body_params( $input );

		$response = $this->server->olp_introspect( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
	}
}
