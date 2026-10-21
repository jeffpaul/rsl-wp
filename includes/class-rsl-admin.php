<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class RSL_Admin {

	private $license_handler;
	private $payment_registry;

	public function __construct() {
		$this->license_handler  = new RSL_License();
		$this->payment_registry = RSL_Payment_Registry::get_instance();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_rsl_save_license', array( $this, 'ajax_save_license' ) );
		add_action(
			'wp_ajax_rsl_delete_license',
			array(
				$this,
				'ajax_delete_license',
			)
		);
		add_action( 'wp_ajax_rsl_generate_xml', array( $this, 'ajax_generate_xml' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post_meta' ) );

		// Add help tabs to admin pages
		add_action(
			'load-toplevel_page_rsl-wp',
			array(
				$this,
				'add_help_tabs_dashboard',
			)
		);
		add_action(
			'load-rsl-wp_page_rsl-licenses',
			array(
				$this,
				'add_help_tabs_licenses',
			)
		);
		add_action(
			'load-rsl-wp_page_rsl-add-license',
			array(
				$this,
				'add_help_tabs_add_license',
			)
		);
		add_action(
			'load-rsl-wp_page_rsl-settings',
			array(
				$this,
				'add_help_tabs_dashboard',
			)
		); // Settings uses same help as dashboard

		// Gutenberg support
		add_action(
			'enqueue_block_editor_assets',
			array(
				$this,
				'enqueue_block_editor_assets',
			)
		);
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	public function add_admin_menu() {
		// Add main menu page with RSL icon - this will be the dashboard
		add_menu_page(
			__( 'RSL Licensing', 'rsl-wp' ),
			__( 'RSL Licensing', 'rsl-wp' ),
			'manage_options',
			'rsl-wp',
			array( $this, 'admin_page' ),
			$this->get_menu_icon(),
			30, // Position after Settings
		);

		// Add submenu pages under RSL Licensing (first submenu will be the same as parent)
		add_submenu_page(
			'rsl-wp',
			__( 'Dashboard', 'rsl-wp' ),
			__( 'Dashboard', 'rsl-wp' ),
			'manage_options',
			'rsl-wp', // Same as parent to avoid duplication
			array( $this, 'admin_page' ),
		);

		add_submenu_page(
			'rsl-wp',
			__( 'All Licenses', 'rsl-wp' ),
			__( 'All Licenses', 'rsl-wp' ),
			'manage_options',
			'rsl-licenses',
			array( $this, 'licenses_page' ),
		);

		add_submenu_page(
			'rsl-wp',
			__( 'Add New License', 'rsl-wp' ),
			__( 'Add New License', 'rsl-wp' ),
			'manage_options',
			'rsl-add-license',
			array( $this, 'add_license_page' ),
		);

		add_submenu_page(
			'rsl-wp',
			__( 'Settings', 'rsl-wp' ),
			__( 'Settings', 'rsl-wp' ),
			'manage_options',
			'rsl-settings',
			array( $this, 'settings_page' ),
		);
	}

	public function register_settings() {
		register_setting(
			'rsl_settings',
			'rsl_global_license_id',
			array(
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_enable_html_injection',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_enable_http_headers',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_enable_robots_txt',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_enable_rss_feed',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_enable_media_metadata',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'rsl_settings',
			'rsl_default_namespace',
			array(
				'sanitize_callback' => 'esc_url_raw',
			)
		);
	}

	public function sanitize_checkbox( $value ) {
		return intval( $value ) ? 1 : 0;
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'rsl' ) !== false ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'postbox' ); // WordPress meta box collapse functionality
			wp_enqueue_script(
				'rsl-admin',
				RSL_PLUGIN_URL . 'admin/js/admin.js',
				array( 'jquery', 'postbox' ),
				RSL_PLUGIN_VERSION,
				true,
			);
			wp_enqueue_style(
				'rsl-admin',
				RSL_PLUGIN_URL . 'admin/css/admin.css',
				array(),
				RSL_PLUGIN_VERSION,
			);

			// Get payment processor info for UI
			$wc_processor           = $this->payment_registry->get_processor(
				'woocommerce',
			);
			$has_payment_capability = $this->payment_registry->has_payment_capability();

			wp_localize_script(
				'rsl-admin',
				'rsl_ajax',
				array(
					'url'                              => admin_url( 'admin-ajax.php' ),
					'nonce'                            => wp_create_nonce( 'rsl_nonce' ),
					'redirect_url'                     => admin_url( 'admin.php?page=rsl-licenses' ),
					'rest_url'                         => rest_url( 'rsl-olp/v1' ),
					'woocommerce_active'               =>
						$wc_processor && $wc_processor->is_available(),
					'woocommerce_subscriptions_active' =>
						$wc_processor &&
						in_array(
							'subscription',
							$wc_processor->get_supported_payment_types(),
						),
					'has_payment_capability'           => $has_payment_capability,
					'strings'                          => array(
						'saving'               => __( 'Saving...', 'rsl-wp' ),
						'error_occurred'       => __(
							'An error occurred while saving the license.',
							'rsl-wp',
						),
						'error_generating_xml' => __(
							'Error generating XML',
							'rsl-wp',
						),
						/* translators: %s: license name */
						'delete_confirm'       => __(
							'Are you sure you want to delete the license "%s"? This action cannot be undone.',
							'rsl-wp',
						),
						'error_deleting'       => __( 'Error deleting license', 'rsl-wp' ),
						'xml_copied'           => __( 'XML copied to clipboard!', 'rsl-wp' ),
						'copy_failed'          => __(
							'Failed to copy to clipboard. Please select and copy manually.',
							'rsl-wp',
						),
						'validate_url'         => __( 'Please enter a valid URL', 'rsl-wp' ),
						'validate_email'       => __(
							'Please enter a valid email address',
							'rsl-wp',
						),
					),
				)
			);
		}
	}

	public function admin_page() {
		// This is now the main RSL dashboard page
		$licenses          = $this->license_handler->get_licenses();
		$global_license_id = get_option( 'rsl_global_license_id', 0 );
		$total_licenses    = count( $licenses );
		$active_licenses   = count(
			array_filter(
				$licenses,
				function ( $license ) {
					return $license['active'] == 1;
				}
			),
		);

		include RSL_PLUGIN_PATH . 'admin/templates/admin-dashboard.php';
	}

	public function settings_page() {
		$licenses          = $this->license_handler->get_licenses();
		$global_license_id = get_option( 'rsl_global_license_id', 0 );

		include RSL_PLUGIN_PATH . 'admin/templates/admin-settings.php';
	}

	public function licenses_page() {
		$licenses = $this->license_handler->get_licenses();

		include RSL_PLUGIN_PATH . 'admin/templates/admin-licenses.php';
	}

	public function add_license_page() {
		$license_data = array();
		$license_id   = 0;

		if (
			isset( $_GET['edit'] ) &&
			is_numeric( $_GET['edit'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'edit_license' )
		) {
			$license_id   = intval( $_GET['edit'] );
			$license_data = $this->license_handler->get_license( $license_id );
		}

		// Get payment processor information for the UI
		$wc_processor                     = $this->payment_registry->get_processor( 'woocommerce' );
		$woocommerce_active               = $wc_processor && $wc_processor->is_available();
		$woocommerce_subscriptions_active =
			$wc_processor &&
			in_array(
				'subscription',
				$wc_processor->get_supported_payment_types(),
			);
		$has_payment_capability           = $this->payment_registry->has_payment_capability();

		include RSL_PLUGIN_PATH . 'admin/templates/admin-add-license.php';
	}

	public function ajax_save_license() {
		check_ajax_referer( 'rsl_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'rsl-wp' ) );
		}

		// Validate required fields
		if ( empty( $_POST['name'] ) || empty( $_POST['content_url'] ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Name and Content URL are required fields.',
						'rsl-wp',
					),
				)
			);
			return;
		}

		// --- BEGIN: RSL-safe URL/path validation ---
		$content_url_input = isset( $_POST['content_url'] )
			? sanitize_text_field( wp_unslash( $_POST['content_url'] ) )
			: '';

		// Allow either:
		// 1) Absolute URL (http/https), OR
		// 2) Server-relative path per RFC 9309 patterns, including * and $ (e.g., "/", "/images/*", "*.pdf", "/api/*$").
		$looks_absolute        = preg_match( '#^https?://#i', $content_url_input ) === 1;
		$looks_server_relative =
			strlen( $content_url_input ) > 0 && $content_url_input[0] === '/';

		if ( $looks_absolute ) {
			$content_url = esc_url_raw( $content_url_input );
			if ( empty( $content_url ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid Content URL format.', 'rsl-wp' ),
					)
				);
				return;
			}
		} elseif ( $looks_server_relative ) {
			// Basic character allowlist per robots-style patterns; allow RFC3986 pchar + '*' and '$'
			if (
				! preg_match(
					'#^/[A-Za-z0-9._~!\'()*+,;=:@/\-%]*\*?\$?$#',
					$content_url_input,
				)
			) {
				wp_send_json_error(
					array(
						'message' => __(
							'Invalid server-relative path/pattern.',
							'rsl-wp',
						),
					)
				);
				return;
			}
			$content_url = $content_url_input; // store as provided (e.g., "/images/*")
		} else {
			wp_send_json_error(
				array(
					'message' => __(
						'Content URL must be an absolute URL (http/https) or a server-relative path (starting with "/").',
						'rsl-wp',
					),
				)
			);
			return;
		}
		// --- END: RSL-safe URL/path validation ---

		$server_url = isset( $_POST['server_url'] )
			? esc_url_raw( wp_unslash( $_POST['server_url'] ) )
			: '';
		if (
			! empty( $server_url ) &&
			! filter_var( $server_url, FILTER_VALIDATE_URL )
		) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid Server URL format.', 'rsl-wp' ),
				)
			);
			return;
		}

		// Validate amount
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		if ( $amount < 0 || $amount > 999999.99 ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Amount must be between 0 and 999,999.99.',
						'rsl-wp',
					),
				)
			);
			return;
		}

		// Validate currency code
		$currency = isset( $_POST['currency'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) )
			: '';
		if ( ! empty( $currency ) && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Currency must be a valid 3-letter ISO code (e.g., USD, EUR).',
						'rsl-wp',
					),
				)
			);
			return;
		}

		// Validate payment processor availability for paid licenses
		$payment_type = isset( $_POST['payment_type'] )
			? sanitize_text_field( wp_unslash( $_POST['payment_type'] ) )
			: 'free';
		if ( $amount > 0 ) {
			$processor = $this->payment_registry->get_processor_for_license(
				array(
					'payment_type' => $payment_type,
					'amount'       => $amount,
				)
			);

			if ( ! $processor ) {
				// Generate WooCommerce-first error message
				$wc_processor = $this->payment_registry->get_processor(
					'woocommerce',
				);

				if ( ! $wc_processor ) {
					$message = __(
						'WooCommerce is required for paid licensing (amount > $0). ',
						'rsl-wp',
					);

					if ( $payment_type === 'attribution' ) {
						$message .= __(
							'For paid attribution licenses, please install and activate WooCommerce, then set up your preferred payment gateway (Stripe, PayPal, etc.).',
							'rsl-wp',
						);
					} else {
						$message .= __(
							'Please install and activate WooCommerce to enable payment processing.',
							'rsl-wp',
						);
					}
				} else {
					$message = sprintf(
						/* translators: %1$s: payment type, %2$s: supported payment types */
						__(
							'The %1$s payment method is not supported. WooCommerce supports %2$s payment types. ',
							'rsl-wp',
						),
						$payment_type,
						implode(
							', ',
							$wc_processor->get_supported_payment_types(),
						),
					);

					if ( $payment_type === 'attribution' ) {
						/* translators: %s will be replaced with "$0" */
						$message .= __(
							'For attribution licenses, set the amount to $0 or use "purchase" payment type.',
							'rsl-wp',
						);
					}
				}

				wp_send_json_error(
					array(
						'message' => $message,
					)
				);
				return;
			}
		}

		// Validate email
		$contact_email = isset( $_POST['contact_email'] )
			? sanitize_email( wp_unslash( $_POST['contact_email'] ) )
			: '';
		if ( ! empty( $contact_email ) && ! is_email( $contact_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid email address format.', 'rsl-wp' ),
				)
			);
			return;
		}

		// Validate payment type
		$allowed_payment_types = array(
			'free',
			'purchase',
			'subscription',
			'training',
			'crawl',
			'inference',
			'attribution',
			'royalty',
		);
		$payment_type          = isset( $_POST['payment_type'] )
			? sanitize_text_field( wp_unslash( $_POST['payment_type'] ) )
			: 'free';
		if ( ! in_array( $payment_type, $allowed_payment_types ) ) {
			$payment_type = 'free';
		}

		$license_data = array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ) ),
			'description'      => isset( $_POST['description'] )
				? sanitize_textarea_field( wp_unslash( $_POST['description'] ) )
				: '',
			'content_url'      => $content_url,
			'server_url'       => $server_url,
			'encrypted'        => isset( $_POST['encrypted'] ) ? 1 : 0,
			'lastmod'          => current_time( 'mysql' ),
			'permits_usage'    => isset( $_POST['permits_usage'] )
				? sanitize_text_field( wp_unslash( $_POST['permits_usage'] ) )
				: '',
			'permits_user'     => isset( $_POST['permits_user'] )
				? sanitize_text_field( wp_unslash( $_POST['permits_user'] ) )
				: '',
			'permits_geo'      => isset( $_POST['permits_geo'] )
				? sanitize_text_field( wp_unslash( $_POST['permits_geo'] ) )
				: '',
			'prohibits_usage'  => isset( $_POST['prohibits_usage'] )
				? sanitize_text_field( wp_unslash( $_POST['prohibits_usage'] ) )
				: '',
			'prohibits_user'   => isset( $_POST['prohibits_user'] )
				? sanitize_text_field( wp_unslash( $_POST['prohibits_user'] ) )
				: '',
			'prohibits_geo'    => isset( $_POST['prohibits_geo'] )
				? sanitize_text_field( wp_unslash( $_POST['prohibits_geo'] ) )
				: '',
			'payment_type'     => $payment_type,
			'standard_url'     => isset( $_POST['standard_url'] )
				? esc_url_raw( wp_unslash( $_POST['standard_url'] ) )
				: '',
			'custom_url'       => isset( $_POST['custom_url'] )
				? esc_url_raw( wp_unslash( $_POST['custom_url'] ) )
				: '',
			'amount'           => $amount,
			'currency'         => $currency,
			'warranty'         => isset( $_POST['warranty'] )
				? sanitize_text_field( wp_unslash( $_POST['warranty'] ) )
				: '',
			'disclaimer'       => isset( $_POST['disclaimer'] )
				? sanitize_text_field( wp_unslash( $_POST['disclaimer'] ) )
				: '',
			'schema_url'       => isset( $_POST['schema_url'] )
				? esc_url_raw( wp_unslash( $_POST['schema_url'] ) )
				: '',
			'copyright_holder' => isset( $_POST['copyright_holder'] )
				? sanitize_text_field( wp_unslash( $_POST['copyright_holder'] ) )
				: '',
			'copyright_type'   => isset( $_POST['copyright_type'] )
				? sanitize_text_field( wp_unslash( $_POST['copyright_type'] ) )
				: '',
			'contact_email'    => $contact_email,
			'contact_url'      => isset( $_POST['contact_url'] )
				? esc_url_raw( wp_unslash( $_POST['contact_url'] ) )
				: '',
			'terms_url'        => isset( $_POST['terms_url'] )
				? esc_url_raw( wp_unslash( $_POST['terms_url'] ) )
				: '',
			'active'           => isset( $_POST['active'] ) ? 1 : 0,
		);

		if (
			isset( $_POST['license_id'] ) &&
			is_numeric( $_POST['license_id'] ) &&
			$_POST['license_id'] > 0
		) {
			$license_id = intval( wp_unslash( $_POST['license_id'] ) );
			$result     = $this->license_handler->update_license(
				$license_id,
				$license_data,
			);
		} else {
			$license_id = $this->license_handler->create_license( $license_data );
			$result     = $license_id !== false;
		}

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'    => __( 'License saved successfully', 'rsl-wp' ),
					'license_id' => $license_id,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to save license', 'rsl-wp' ),
				)
			);
		}
	}

	public function ajax_delete_license() {
		check_ajax_referer( 'rsl_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'rsl-wp' ) );
		}

		$license_id = isset( $_POST['license_id'] )
			? intval( $_POST['license_id'] )
			: 0;

		if ( $this->license_handler->delete_license( $license_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'License deleted successfully', 'rsl-wp' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete license', 'rsl-wp' ),
				)
			);
		}
	}

	public function ajax_generate_xml() {
		check_ajax_referer( 'rsl_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'rsl-wp' ) );
		}

		$license_id   = isset( $_POST['license_id'] )
			? intval( $_POST['license_id'] )
			: 0;
		$license_data = $this->license_handler->get_license( $license_id );

		if ( $license_data ) {
			$xml = $this->license_handler->generate_rsl_xml( $license_data );
			wp_send_json_success(
				array(
					'xml' => $xml,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'License not found', 'rsl-wp' ),
				)
			);
		}
	}

	public function add_meta_boxes() {
		// Only add meta boxes for classic editor (non-Gutenberg)
		// Gutenberg uses the native Document Settings Panel instead
		$screen = get_current_screen();

		if (
			$screen &&
			method_exists( $screen, 'is_block_editor' ) &&
			$screen->is_block_editor()
		) {
			return; // Skip meta boxes for Gutenberg
		}

		$post_types = array( 'post', 'page' );
		$post_types = apply_filters( 'rsl_supported_post_types', $post_types );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'rsl_license_meta',
				__( 'RSL License', 'rsl-wp' ),
				array( $this, 'meta_box_callback' ),
				$post_type,
				'side',
				'default',
			);
		}
	}

	public function meta_box_callback( $post ) {
		wp_nonce_field( 'rsl_meta_box', 'rsl_meta_nonce' );

		$licenses             = $this->license_handler->get_licenses();
		$selected_license     = get_post_meta( $post->ID, '_rsl_license_id', true );
		$override_content_url = get_post_meta(
			$post->ID,
			'_rsl_override_content_url',
			true,
		);
		$global_license_id    = get_option( 'rsl_global_license_id', 0 );

		// Show global license info if configured
		if ( $global_license_id > 0 && empty( $selected_license ) ) {
			$global_license = $this->license_handler->get_license(
				$global_license_id,
			);
			if ( $global_license ) {
				echo '<div style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 8px; margin-bottom: 12px; border-radius: 4px;">';
				echo '<strong>' .
					esc_html( __( 'Global License Active:', 'rsl-wp' ) ) .
					'</strong><br>';
				echo '<small>' .
					esc_html( $global_license['name'] ) .
					' (' .
					esc_html( $global_license['payment_type'] ) .
					')</small>';
				echo '</div>';
			}
		}

		echo '<p><label for="rsl_license_select">' .
			esc_html( __( 'License Override:', 'rsl-wp' ) ) .
			'</label></p>';
		echo '<select id="rsl_license_select" name="rsl_license_id" style="width: 100%;">';
		echo '<option value="">' .
			esc_html( __( 'Use Global License', 'rsl-wp' ) ) .
			'</option>';

		foreach ( $licenses as $license ) {
			$selected = selected( $selected_license, $license['id'], false );
			echo '<option value="' .
				esc_attr( $license['id'] ) .
				'" ' .
				esc_attr( $selected ) .
				'>';
			echo esc_html( $license['name'] ) .
				' (' .
				esc_html( $license['payment_type'] ) .
				')';
			echo '</option>';
		}

		echo '</select>';
		echo '<p><small>' .
			esc_html(
				__(
					'Select a specific license to override the global license for this content.',
					'rsl-wp',
				),
			) .
			'</small></p>';

		echo '<p style="margin-top: 15px;"><label for="rsl_override_url">';
		echo esc_html( __( 'Override Content URL:', 'rsl-wp' ) );
		echo '</label></p>';
		echo '<input type="url" id="rsl_override_url" name="rsl_override_content_url" ';
		echo 'value="' .
			esc_attr( $override_content_url ) .
			'" style="width: 100%;" ';
		echo 'placeholder="' .
			esc_attr( __( 'Leave empty to use post URL', 'rsl-wp' ) ) .
			'">';

		echo '<p><small>' .
			esc_html(
				__(
					'Override the content URL for this specific post/page. Useful for syndicated content.',
					'rsl-wp',
				),
			) .
			'</small></p>';
	}

	public function save_post_meta( $post_id ) {
		// Skip if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if this is a REST API request (Gutenberg saves via REST)
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// For Gutenberg, meta is saved automatically via REST API
			// We just need to validate the data
			$this->validate_post_meta( $post_id );
			return;
		}

		// Classic editor nonce verification
		if (
			! isset( $_POST['rsl_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['rsl_meta_nonce'] ) ),
				'rsl_meta_box',
			)
		) {
			return;
		}

		// Process classic editor form data
		$license_id   = isset( $_POST['rsl_license_id'] )
			? intval( $_POST['rsl_license_id'] )
			: 0;
		$override_url = isset( $_POST['rsl_override_content_url'] )
			? esc_url_raw( wp_unslash( $_POST['rsl_override_content_url'] ) )
			: '';

		// Validate override URL if provided
		if (
			! empty( $override_url ) &&
			! filter_var( $override_url, FILTER_VALIDATE_URL )
		) {
			// Invalid URL, don't save it
			$override_url = '';
		}

		if ( $license_id > 0 ) {
			update_post_meta( $post_id, '_rsl_license_id', $license_id );
		} else {
			delete_post_meta( $post_id, '_rsl_license_id' );
		}

		if ( ! empty( $override_url ) ) {
			update_post_meta(
				$post_id,
				'_rsl_override_content_url',
				$override_url,
			);
		} else {
			delete_post_meta( $post_id, '_rsl_override_content_url' );
		}

		$this->validate_post_meta( $post_id );
	}

	private function validate_post_meta( $post_id ) {
		// Validate license ID exists if set
		$license_id = get_post_meta( $post_id, '_rsl_license_id', true );
		if ( $license_id > 0 ) {
			$license = $this->license_handler->get_license( $license_id );
			if ( ! $license || ! $license['active'] ) {
				// Invalid license, remove it
				delete_post_meta( $post_id, '_rsl_license_id' );
				// error_log('RSL: Removed invalid license ID ' . $license_id . ' from post ' . $post_id);
			}
		}

		// Validate override URL format
		$override_url = get_post_meta(
			$post_id,
			'_rsl_override_content_url',
			true,
		);
		if (
			! empty( $override_url ) &&
			! filter_var( $override_url, FILTER_VALIDATE_URL )
		) {
			delete_post_meta( $post_id, '_rsl_override_content_url' );
			// error_log('RSL: Removed invalid override URL from post ' . $post_id);
		}
	}

	private function get_menu_icon() {
		// RSL SVG icon as base64 data URI for WordPress admin menu
		$svg_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20">
  <defs>
    <mask id="text-mask">
      <rect width="100%" height="100%" fill="white"/>
      <text x="10" y="14" font-family="Arial, sans-serif" font-size="10" font-weight="bold"
            fill="black" text-anchor="middle">RSL</text>
    </mask>
  </defs>
  <!-- Shield shape with applied mask -->
  <path d="M0 4 L10 0 L20 4 L18 16 L10 20 L2 16 Z" fill="#E44D26" mask="url(#text-mask)"/>
  <path d="M10 0 L20 4 L18 16 L10 20 Z" fill="#F16529" mask="url(#text-mask)"/>
</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg_icon );
	}

	public function register_meta_fields() {
		$post_types = array( 'post', 'page' );
		$post_types = apply_filters( 'rsl_supported_post_types', $post_types );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'_rsl_license_id',
				array(
					'type'          => 'integer',
					'description'   => 'RSL License ID for this post',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				'_rsl_override_content_url',
				array(
					'type'          => 'string',
					'description'   => 'Override content URL for RSL license',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	public function enqueue_block_editor_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		wp_enqueue_script(
			'rsl-gutenberg',
			RSL_PLUGIN_URL . 'admin/js/gutenberg.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
			),
			RSL_PLUGIN_VERSION,
			true,
		);

		// Pass license data to JavaScript
		$licenses          = $this->license_handler->get_licenses();
		$global_license_id = get_option( 'rsl_global_license_id', 0 );

		wp_localize_script(
			'rsl-gutenberg',
			'rslGutenberg',
			array(
				'licenses'        => $licenses,
				'globalLicenseId' => $global_license_id,
				'nonce'           => wp_create_nonce( 'rsl_nonce' ),
			)
		);
	}

	// === Help Tab Methods ===

	public function add_help_tabs_dashboard() {
		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-overview',
				'title'   => __( 'RSL Overview', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'Really Simple Licensing (RSL)', 'rsl-wp' ) .
					'</h3>
                <p>' .
					__(
						'RSL enables you to define machine-readable licensing terms for your content. This helps AI companies, search engines, and other automated systems understand how they can use your content.',
						'rsl-wp',
					) .
					'</p>
                <h4>' .
					__( 'Integration Methods', 'rsl-wp' ) .
					'</h4>
                <ul>
                    <li><strong>' .
					__( 'HTML Head Injection', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Embed licenses in page headers', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'HTTP Headers', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Add Link headers to responses', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'robots.txt Integration', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Extend robots.txt with RSL directives', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'RSS Enhancement', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Add licensing to feed items', 'rsl-wp' ) .
					'</li>
                </ul>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-quick-start',
				'title'   => __( 'Quick Start', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'Getting Started', 'rsl-wp' ) .
					'</h3>
                <ol>
                    <li>' .
					__(
						'Create your first license using "Add New License"',
						'rsl-wp',
					) .
					'</li>
                    <li>' .
					__( 'Configure license terms and permissions', 'rsl-wp' ) .
					'</li>
                    <li>' .
					__( 'Set it as your global license', 'rsl-wp' ) .
					'</li>
                    <li>' .
					__(
						'Enable integration methods (HTML, robots.txt, etc.)',
						'rsl-wp',
					) .
					'</li>
                    <li>' .
					__( 'Save settings', 'rsl-wp' ) .
					'</li>
                </ol>
                <p>' .
					__(
						'Your site will now broadcast machine-readable licensing terms!',
						'rsl-wp',
					) .
					'</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' .
				__( 'RSL Resources', 'rsl-wp' ) .
				'</strong></p>
            <p><a href="https://rslstandard.org" target="_blank">' .
				__( 'RSL Standard Documentation', 'rsl-wp' ) .
				'</a></p>
            <p><a href="https://github.com/jeffpaul/rsl-wp" target="_blank">' .
				__( 'Plugin Documentation', 'rsl-wp' ) .
				'</a></p>',
		);
	}

	public function add_help_tabs_licenses() {
		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-license-management',
				'title'   => __( 'License Management', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'Managing Your Licenses', 'rsl-wp' ) .
					'</h3>
                <p>' .
					__(
						'This page shows all your RSL licenses. You can create multiple licenses for different content areas or use cases.',
						'rsl-wp',
					) .
					'</p>
                <h4>' .
					__( 'License Actions', 'rsl-wp' ) .
					'</h4>
                <ul>
                    <li><strong>' .
					__( 'Edit', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Modify license terms and settings', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Generate XML', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Download the RSL XML for this license', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Delete', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Remove the license permanently', 'rsl-wp' ) .
					'</li>
                </ul>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-payment-types',
				'title'   => __( 'Payment Types', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'Understanding Payment Types', 'rsl-wp' ) .
					'</h3>
                <ul>
                    <li><strong>' .
					__( 'Free', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'No payment required', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Purchase', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'One-time payment', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Subscription', 'rsl-wp' ) .
					'</strong>: ' .
					__(
						'Recurring payments (requires WooCommerce Subscriptions)',
						'rsl-wp',
					) .
					'</li>
                    <li><strong>' .
					__( 'Attribution', 'rsl-wp' ) .
					'</strong>: ' .
					__(
						'Credit/attribution required (can be free or paid)',
						'rsl-wp',
					) .
					'</li>
                    <li><strong>' .
					__( 'Training', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'AI training-specific licensing', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Crawl', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Web crawling permissions', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Inference', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'AI inference usage rights', 'rsl-wp' ) .
					'</li>
                </ul>
                <p><em>' .
					__(
						'Note: Set amount to $0 for free licenses of any type. Amounts > $0 require WooCommerce for payment processing.',
						'rsl-wp',
					) .
					'</em></p>',
			)
		);
	}

	public function add_help_tabs_add_license() {
		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-license-creation',
				'title'   => __( 'Creating Licenses', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'License Creation Guide', 'rsl-wp' ) .
					'</h3>
                <h4>' .
					__( 'Required Fields', 'rsl-wp' ) .
					'</h4>
                <ul>
                    <li><strong>' .
					__( 'Name', 'rsl-wp' ) .
					'</strong>: ' .
					__( 'Descriptive name for this license', 'rsl-wp' ) .
					'</li>
                    <li><strong>' .
					__( 'Content URL', 'rsl-wp' ) .
					'</strong>: ' .
					__(
						'URL pattern this license covers (e.g., "/", "/blog/*", "*.pdf")',
						'rsl-wp',
					) .
					'</li>
                </ul>
                <h4>' .
					__( 'URL Patterns', 'rsl-wp' ) .
					'</h4>
                <ul>
                    <li><code>/</code> - ' .
					__( 'Entire site', 'rsl-wp' ) .
					'</li>
                    <li><code>/blog/*</code> - ' .
					__( 'Blog directory and subdirectories', 'rsl-wp' ) .
					'</li>
                    <li><code>*.pdf</code> - ' .
					__( 'All PDF files', 'rsl-wp' ) .
					'</li>
                    <li><code>/api/*$</code> - ' .
					__( 'API endpoints (end anchor)', 'rsl-wp' ) .
					'</li>
                </ul>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rsl-woocommerce-setup',
				'title'   => __( 'WooCommerce Setup', 'rsl-wp' ),
				'content' =>
					'<h3>' .
					__( 'Setting Up Paid Licensing', 'rsl-wp' ) .
					'</h3>
                <p>' .
					__(
						'For paid licensing (amount > $0), you need WooCommerce installed and configured.',
						'rsl-wp',
					) .
					'</p>
                <h4>' .
					__( 'Setup Steps', 'rsl-wp' ) .
					'</h4>
                <ol>
                    <li>' .
					__( 'Install and activate WooCommerce plugin', 'rsl-wp' ) .
					'</li>
                    <li>' .
					__( 'Complete WooCommerce setup wizard', 'rsl-wp' ) .
					'</li>
                    <li>' .
					__(
						'Configure payment gateways (Stripe, PayPal, etc.)',
						'rsl-wp',
					) .
					'</li>
                    <li>' .
					__( 'Create paid license with amount > $0', 'rsl-wp' ) .
					'</li>
                    <li>' .
					__( 'Set Server URL to built-in server option', 'rsl-wp' ) .
					'</li>
                </ol>
                <p>' .
					__(
						'The plugin will automatically create hidden WooCommerce products for your licenses and handle the complete payment-to-token flow.',
						'rsl-wp',
					) .
					'</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' .
				__( 'Need Help?', 'rsl-wp' ) .
				'</strong></p>
            <p><a href="https://rslstandard.org" target="_blank">' .
				__( 'RSL Standard Docs', 'rsl-wp' ) .
				'</a></p>
            <p><a href="https://github.com/jeffpaul/rsl-wp/blob/main/docs/PAYMENTS.md" target="_blank">' .
				__( 'Payment Setup Guide', 'rsl-wp' ) .
				'</a></p>
            <p><a href="https://github.com/jeffpaul/rsl-wp/blob/main/docs/DEVELOPER.md" target="_blank">' .
				__( 'Developer Guide', 'rsl-wp' ) .
				'</a></p>',
		);
	}
}
