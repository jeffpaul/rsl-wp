<?php
/**
 * Plugin Name:       Really Simple Licensing
 * Plugin URI:        https://github.com/jeffpaul/rsl-wp
 * Description:       Complete Really Simple Licensing (RSL) support for WordPress sites. Define machine-readable licensing terms for your content, enabling AI companies and crawlers to properly license your digital assets.
 * Version:           0.0.5
 * Author:            James W. LePage, Jeffrey Paul
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rsl-wp
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Debug logging helper function
 * Only logs when WP_DEBUG and RSL_DEBUG are enabled
 */
if ( ! function_exists( 'rsl_log' ) ) {
	function rsl_log( $message, $level = 'info' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'RSL_DEBUG' ) && RSL_DEBUG ) {
			error_log( sprintf( '[RSL %s] %s', strtoupper( $level ), $message ) );
		}
	}
}

define( 'RSL_PLUGIN_VERSION', '0.0.5' );
define( 'RSL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RSL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RSL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

class RSL_Licensing {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Add plugin action links
		add_filter( 'plugin_action_links_' . RSL_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
	}

	public function init() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		// Optional composer autoload (JWT library)
		$autoload = RSL_PLUGIN_PATH . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-license.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-admin.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-frontend.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-robots.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-rss.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-media.php';

		// Load OAuth client management and rate limiting
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-oauth-client.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-rate-limiter.php';

		// Load modular payment system
		require_once RSL_PLUGIN_PATH . 'includes/interfaces/interface-rsl-payment-processor.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-payment-registry.php';
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-session-manager.php';

		// Load server (depends on payment system)
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-server.php';

		// Load WordPress Abilities API integration
		require_once RSL_PLUGIN_PATH . 'includes/class-rsl-abilities.php';
	}

	private function init_hooks() {
		if ( is_admin() ) {
			new RSL_Admin();
		}

		new RSL_Frontend();
		new RSL_Robots();
		new RSL_RSS();
		new RSL_Media();
		new RSL_Server();

		// Initialize WordPress Abilities API integration
		if ( function_exists( 'wp_register_ability' ) ) {
			new RSL_Abilities();
		}
	}

	public function activate() {
		if ( ! $this->create_tables() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'RSL Licensing: Failed to create required database tables. Please check database permissions.', 'rsl-wp' ) );
		}

		if ( ! $this->create_default_options() ) {
			rsl_log( 'Failed to create default options during activation', 'warning' );
		}

		if ( ! $this->seed_global_license() ) {
			rsl_log( 'Failed to create default license during activation', 'warning' );
		}

		if ( ! $this->create_database_indexes() ) {
			rsl_log( 'Failed to create database indexes during activation', 'warning' );
		}

		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function add_plugin_action_links( $links ) {
		$action_links = array(
			'dashboard' => '<a href="' . admin_url( 'admin.php?page=rsl-licensing' ) . '">' . __( 'Dashboard', 'rsl-wp' ) . '</a>',
			'settings'  => '<a href="' . admin_url( 'admin.php?page=rsl-settings' ) . '">' . __( 'Settings', 'rsl-wp' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'rsl_licenses';

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            content_url text NOT NULL,
            server_url varchar(255),
            encrypted tinyint(1) DEFAULT 0,
            lastmod datetime,
            permits_usage text,
            permits_user text,
            permits_geo text,
            prohibits_usage text,
            prohibits_user text,
            prohibits_geo text,
            payment_type varchar(50) DEFAULT 'free',
            standard_url varchar(255),
            custom_url varchar(255),
            amount decimal(10,2),
            currency varchar(3),
            warranty text,
            disclaimer text,
            schema_url varchar(255),
            copyright_holder varchar(255),
            copyright_type varchar(20),
            contact_email varchar(255),
            contact_url varchar(255),
            terms_url varchar(255),
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		// Verify table was created successfully (no caching needed for one-time setup)
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			// error_log('RSL: Failed to create database table: ' . $table_name);
			if ( $wpdb->last_error ) {
				rsl_log( 'Database error: ' . $wpdb->last_error, 'error' );
			}
			return false;
		}

		// Create OAuth clients table
		$oauth_table = $wpdb->prefix . 'rsl_oauth_clients';
		$oauth_sql   = "CREATE TABLE $oauth_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            client_id varchar(100) NOT NULL UNIQUE,
            client_secret_hash varchar(255) NOT NULL,
            client_name varchar(255) NOT NULL,
            redirect_uris text,
            grant_types varchar(255) DEFAULT 'client_credentials',
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_client_id (client_id)
        ) $charset_collate;";

		$oauth_result = dbDelta( $oauth_sql );

		// Verify OAuth table was created (no caching needed for one-time setup)
		$oauth_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$oauth_table
			)
		);

		if ( ! $oauth_table_exists ) {
			// error_log('RSL: Failed to create OAuth clients table: ' . $oauth_table);
			if ( $wpdb->last_error ) {
				rsl_log( 'Database error: ' . $wpdb->last_error, 'error' );
			}
			return false;
		}

		// Create tokens revocation table for jti tracking
		$tokens_table = $wpdb->prefix . 'rsl_tokens';
		$tokens_sql   = "CREATE TABLE $tokens_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            jti varchar(100) NOT NULL UNIQUE,
            client_id varchar(100) NOT NULL,
            license_id mediumint(9) NOT NULL,
            order_id mediumint(9) NULL,
            subscription_id mediumint(9) NULL,
            expires_at datetime NOT NULL,
            revoked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_jti (jti),
            KEY idx_client_license (client_id, license_id),
            KEY idx_expires (expires_at)
        ) $charset_collate;";

		$tokens_result = dbDelta( $tokens_sql );

		// Verify tokens table was created (no caching needed for one-time setup)
		$tokens_table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tokens_table
			)
		);

		if ( ! $tokens_table_exists ) {
			// error_log('RSL: Failed to create tokens table: ' . $tokens_table);
			return false;
		}

		return true;
	}

	private function create_default_options() {
		$default_options = array(
			'rsl_global_license_id'     => 0,
			'rsl_enable_html_injection' => 1,
			'rsl_enable_http_headers'   => 1,
			'rsl_enable_robots_txt'     => 1,
			'rsl_enable_rss_feed'       => 1,
			'rsl_enable_media_metadata' => 1,
			'rsl_default_namespace'     => 'https://rslstandard.org/rsl',
		);

		$success = true;
		foreach ( $default_options as $option => $value ) {
			if ( get_option( $option ) === false ) {
				$result = add_option( $option, $value );
				if ( ! $result ) {
					// error_log('RSL: Failed to create option: ' . $option);
					$success = false;
				}
			}
		}

		return $success;
	}

	private function seed_global_license() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_licenses';

		// Try cache first for license count check
		$cache_key         = 'rsl_license_count';
		$existing_licenses = wp_cache_get( $cache_key, 'rsl_licenses' );

		if ( $existing_licenses === false ) {
			$existing_licenses = $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}rsl_licenses`" );

			// Cache successful results for 1 hour
			if ( $existing_licenses !== null && ! $wpdb->last_error ) {
				wp_cache_set( $cache_key, $existing_licenses, 'rsl_licenses', 3600 );
			}
		}

		if ( $wpdb->last_error ) {
			// error_log('RSL: Database error checking existing licenses: ' . $wpdb->last_error);
			return false;
		}

		if ( $existing_licenses == 0 ) {
			$site_url    = home_url();
			$site_name   = get_bloginfo( 'name' );
			$admin_email = get_option( 'admin_email' );

			$default_license = array(
				'name'             => __( 'Default Site License', 'rsl-wp' ),
				'description'      => __( 'Default licensing terms for this WordPress site', 'rsl-wp' ),
				'content_url'      => $site_url,
				'server_url'       => '', // Don't require server authentication for default license
				'encrypted'        => 0,
				'lastmod'          => current_time( 'mysql' ),
				'permits_usage'    => 'search',
				'permits_user'     => 'non-commercial',
				'permits_geo'      => '',
				'prohibits_usage'  => 'train-ai,train-genai',
				'prohibits_user'   => 'commercial',
				'prohibits_geo'    => '',
				'payment_type'     => 'attribution',
				'standard_url'     => '',
				'custom_url'       => '',
				'amount'           => 0,
				'currency'         => 'USD',
				'warranty'         => 'ownership,authority',
				'disclaimer'       => 'as-is,no-warranty',
				'schema_url'       => 'https://rslstandard.org/schema',
				'copyright_holder' => $site_name,
				'copyright_type'   => 'organization',
				'contact_email'    => $admin_email,
				'contact_url'      => $site_url . '/contact',
				'terms_url'        => $site_url . '/terms',
				'active'           => 1,
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			);

			$license_inserted = $wpdb->insert(
				$table_name,
				$default_license,
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%f',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);

			// Clear license cache after successful insert
			if ( $license_inserted && ! $wpdb->last_error ) {
				wp_cache_delete( 'rsl_license_count', 'rsl_licenses' );
				wp_cache_flush_group( 'rsl_licenses' );
			}

			if ( $license_inserted ) {
				$license_id     = $wpdb->insert_id;
				$option_updated = update_option( 'rsl_global_license_id', $license_id );
				if ( ! $option_updated ) {
					// error_log('RSL: Failed to set global license ID: ' . $license_id);
					return false;
				}
				return true;
			} else {
				// error_log('RSL: Failed to insert default license: ' . $wpdb->last_error);
				return false;
			}
		}

		return true; // Licenses already exist
	}

	private function create_database_indexes() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rsl_licenses';

		$indexes = array(
			'idx_active'     => 'active',
			'idx_updated_at' => 'updated_at',
			'idx_name'       => 'name',
		);

		$success = true;

		foreach ( $indexes as $index_name => $column ) {
			// Check if index already exists (no caching needed for one-time setup)
			$index_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW INDEX FROM `{$wpdb->prefix}rsl_licenses` WHERE Key_name = %s",
					$index_name
				)
			);

			if ( ! $index_exists ) {
				$sql = "ALTER TABLE {$table_name} ADD INDEX {$index_name} ({$column})";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $wpdb->query( $sql );

				if ( $result === false ) {
					// error_log('RSL: Failed to create index ' . $index_name . ': ' . $wpdb->last_error);
					$success = false;
				}
			}
		}

		return $success;
	}
}

RSL_Licensing::get_instance();

if ( ! function_exists( 'rsl_has_license' ) ) {
	function rsl_has_license() {
		static $frontend = null;

		if ( $frontend === null ) {
			$frontend = new RSL_Frontend();
		}

		$license_data = $frontend->get_current_page_license();
		return ! empty( $license_data );
	}
}

if ( ! function_exists( 'rsl_get_license_info' ) ) {
	function rsl_get_license_info() {
		static $frontend = null;

		if ( $frontend === null ) {
			$frontend = new RSL_Frontend();
		}

		return $frontend->get_current_page_license();
	}
}
