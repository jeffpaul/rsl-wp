<?php
/**
 * Tests for RSL_Admin class
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_Admin;
use RSL_License;

/**
 * Test RSL_Admin functionality
 *
 * @group unit
 * @group admin
 */
class TestRSLAdmin extends TestCase {

	/**
	 * Admin instance
	 *
	 * @var RSL_Admin
	 */
	private $admin;

	/**
	 * License handler instance
	 *
	 * @var RSL_License
	 */
	private $license_handler;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin           = new RSL_Admin();
		$this->license_handler = new RSL_License();

		// Mock admin environment
		set_current_screen( 'toplevel_page_rsl-licensing' );

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	/**
	 * Test admin menu registration
	 *
	 * @covers RSL_Admin::add_admin_menu
	 */
	public function test_admin_menu_registration() {
		global $menu, $submenu;

		// Reset menu globals
		$menu    = array();
		$submenu = array();

		$this->admin->add_admin_menu();

		// Check main menu page
		$this->assertNotEmpty( $menu );

		// Find RSL Licensing menu item
		$rsl_menu_found = false;
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[0] ) && strpos( $menu_item[0], 'RSL Licensing' ) !== false ) {
				$rsl_menu_found = true;
				break;
			}
		}
		$this->assertTrue( $rsl_menu_found, 'RSL Licensing main menu should be registered' );

		// Check submenu pages
		$this->assertArrayHasKey( 'rsl-wp', $submenu );
		$this->assertIsArray( $submenu['rsl-wp'] );

		$expected_submenus = array( 'Dashboard', 'All Licenses', 'Add New License', 'Settings' );
		foreach ( $expected_submenus as $expected ) {
			$found = false;
			foreach ( $submenu['rsl-wp'] as $submenu_item ) {
				if ( strpos( $submenu_item[0], $expected ) !== false ) {
					$found = true;
					break;
				}
			}
			$this->assertTrue( $found, "Submenu '{$expected}' should be registered" );
		}
	}

	/**
	 * Test settings registration
	 *
	 * @covers RSL_Admin::register_settings
	 */
	public function test_settings_registration() {
		$this->admin->register_settings();

		// Check if settings are registered
		$registered_settings = get_registered_settings();

		$expected_settings = array(
			'rsl_global_license_id',
			'rsl_enable_html_injection',
			'rsl_enable_http_headers',
			'rsl_enable_robots_txt',
			'rsl_enable_rss_feed',
			'rsl_enable_media_metadata',
		);

		foreach ( $expected_settings as $setting ) {
			$this->assertArrayHasKey( $setting, $registered_settings, "Setting '{$setting}' should be registered" );
		}
	}

	/**
	 * Test checkbox sanitization
	 *
	 * @covers RSL_Admin::sanitize_checkbox
	 */
	public function test_checkbox_sanitization() {
		// Test truthy values
		$truthy_values = array( 1, '1', 'yes', 'on', true );
		foreach ( $truthy_values as $value ) {
			$result = $this->admin->sanitize_checkbox( $value );
			$this->assertEquals( 1, $result, "Value '{$value}' should be sanitized to 1" );
		}

		// Test falsy values
		$falsy_values = array( 0, '0', '', 'no', 'off', false, null );
		foreach ( $falsy_values as $value ) {
			$result = $this->admin->sanitize_checkbox( $value );
			$this->assertEquals( 0, $result, "Value '{$value}' should be sanitized to 0" );
		}
	}

	/**
	 * Test AJAX license save
	 *
	 * @covers RSL_Admin::ajax_save_license
	 */
	public function test_ajax_save_license() {
		// Mock AJAX request
		$_POST['nonce']        = wp_create_nonce( 'rsl_admin_nonce' );
		$_POST['license_data'] = json_encode(
			array(
				'name'         => 'AJAX Test License',
				'description'  => 'License created via AJAX',
				'content_url'  => '/ajax-test',
				'payment_type' => 'free',
			)
		);

		// Mock wp_die to capture output
		$wp_die_called = false;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				$wp_die_called = true;
				return function ( $message ) {
					echo $message;
					throw new \Exception( 'wp_die called' );
				};
			}
		);

		ob_start();
		try {
			$this->admin->ajax_save_license();
		} catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'wp_die called' ) {
				throw $e;
			}
		}
		$output = ob_get_clean();

		$this->assertTrue( $wp_die_called );
		$this->assertValidJson( $output );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'license_id', $response['data'] );
	}

	/**
	 * Test AJAX license save with invalid data
	 *
	 * @covers RSL_Admin::ajax_save_license
	 */
	public function test_ajax_save_license_invalid_data() {
		// Mock AJAX request with invalid data
		$_POST['nonce']        = wp_create_nonce( 'rsl_admin_nonce' );
		$_POST['license_data'] = json_encode(
			array(
				// Missing required 'name' field
				'content_url' => '/invalid-test',
			)
		);

		$wp_die_called = false;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				$wp_die_called = true;
				return function ( $message ) {
					echo $message;
					throw new \Exception( 'wp_die called' );
				};
			}
		);

		ob_start();
		try {
			$this->admin->ajax_save_license();
		} catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'wp_die called' ) {
				throw $e;
			}
		}
		$output = ob_get_clean();

		$this->assertTrue( $wp_die_called );
		$this->assertValidJson( $output );

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
	}

	/**
	 * Test AJAX license save without proper nonce
	 *
	 * @covers RSL_Admin::ajax_save_license
	 */
	public function test_ajax_save_license_invalid_nonce() {
		// Mock AJAX request with invalid nonce
		$_POST['nonce']        = 'invalid-nonce';
		$_POST['license_data'] = json_encode(
			array(
				'name' => 'Test License',
			)
		);

		$wp_die_called = false;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				$wp_die_called = true;
				return function ( $message ) {
					echo $message;
					throw new \Exception( 'wp_die called' );
				};
			}
		);

		ob_start();
		try {
			$this->admin->ajax_save_license();
		} catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'wp_die called' ) {
				throw $e;
			}
		}
		$output = ob_get_clean();

		$this->assertTrue( $wp_die_called );
		$this->assertValidJson( $output );

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'message', $response['data'] );
	}

	/**
	 * Test AJAX license deletion
	 *
	 * @covers RSL_Admin::ajax_delete_license
	 */
	public function test_ajax_delete_license() {
		// Create test license
		$license_id = $this->create_test_license(
			array(
				'name' => 'Delete Test License',
			)
		);

		// Mock AJAX request
		$_POST['nonce']      = wp_create_nonce( 'rsl_admin_nonce' );
		$_POST['license_id'] = $license_id;

		$wp_die_called = false;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				$wp_die_called = true;
				return function ( $message ) {
					echo $message;
					throw new \Exception( 'wp_die called' );
				};
			}
		);

		ob_start();
		try {
			$this->admin->ajax_delete_license();
		} catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'wp_die called' ) {
				throw $e;
			}
		}
		$output = ob_get_clean();

		$this->assertTrue( $wp_die_called );
		$this->assertValidJson( $output );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );

		// Verify license was deleted
		$deleted_license = $this->license_handler->get_license( $license_id );
		$this->assertNull( $deleted_license );
	}

	/**
	 * Test AJAX XML generation
	 *
	 * @covers RSL_Admin::ajax_generate_xml
	 */
	public function test_ajax_generate_xml() {
		$license_id = $this->create_test_license(
			array(
				'name'        => 'XML Generation Test',
				'content_url' => '/xml-gen-test',
			)
		);

		// Mock AJAX request
		$_POST['nonce']      = wp_create_nonce( 'rsl_admin_nonce' );
		$_POST['license_id'] = $license_id;

		$wp_die_called = false;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				$wp_die_called = true;
				return function ( $message ) {
					echo $message;
					throw new \Exception( 'wp_die called' );
				};
			}
		);

		ob_start();
		try {
			$this->admin->ajax_generate_xml();
		} catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'wp_die called' ) {
				throw $e;
			}
		}
		$output = ob_get_clean();

		$this->assertTrue( $wp_die_called );
		$this->assertValidJson( $output );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'xml', $response['data'] );
		$this->assertValidRslXml( $response['data']['xml'] );
	}

	/**
	 * Test post meta box registration
	 *
	 * @covers RSL_Admin::add_meta_boxes
	 */
	public function test_meta_boxes_registration() {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$this->admin->add_meta_boxes();

		// Check if RSL meta box is registered for posts
		$this->assertArrayHasKey( 'post', $wp_meta_boxes );
		$this->assertArrayHasKey( 'side', $wp_meta_boxes['post'] );
		$this->assertArrayHasKey( 'default', $wp_meta_boxes['post']['side'] );

		$rsl_meta_box_found = false;
		foreach ( $wp_meta_boxes['post']['side']['default'] as $meta_box ) {
			if ( isset( $meta_box['title'] ) && strpos( $meta_box['title'], 'RSL' ) !== false ) {
				$rsl_meta_box_found = true;
				break;
			}
		}
		$this->assertTrue( $rsl_meta_box_found, 'RSL meta box should be registered' );
	}

	/**
	 * Test post meta save
	 *
	 * @covers RSL_Admin::save_post_meta
	 */
	public function test_save_post_meta() {
		// Create test license
		$license_id = $this->create_test_license( array( 'name' => 'Meta Test License' ) );

		// Create test post
		$post_id = $this->factory->post->create( array( 'post_title' => 'Meta Test Post' ) );

		// Mock POST data
		$_POST['rsl_license_nonce'] = wp_create_nonce( 'rsl_license_meta_box' );
		$_POST['rsl_license_id']    = $license_id;

		$this->admin->save_post_meta( $post_id );

		// Verify meta was saved
		$saved_license_id = get_post_meta( $post_id, '_rsl_license_id', true );
		$this->assertEquals( $license_id, intval( $saved_license_id ) );
	}

	/**
	 * Test post meta save with invalid nonce
	 *
	 * @covers RSL_Admin::save_post_meta
	 */
	public function test_save_post_meta_invalid_nonce() {
		$license_id = $this->create_test_license( array( 'name' => 'Invalid Nonce Test' ) );
		$post_id    = $this->factory->post->create( array( 'post_title' => 'Invalid Nonce Post' ) );

		// Mock invalid nonce
		$_POST['rsl_license_nonce'] = 'invalid-nonce';
		$_POST['rsl_license_id']    = $license_id;

		$this->admin->save_post_meta( $post_id );

		// Meta should not be saved with invalid nonce
		$saved_license_id = get_post_meta( $post_id, '_rsl_license_id', true );
		$this->assertEmpty( $saved_license_id );
	}

	/**
	 * Test admin scripts enqueuing
	 *
	 * @covers RSL_Admin::enqueue_admin_scripts
	 */
	public function test_admin_scripts_enqueuing() {
		// Mock current screen as RSL admin page
		set_current_screen( 'toplevel_page_rsl-licensing' );

		$this->admin->enqueue_admin_scripts( 'toplevel_page_rsl-licensing' );

		// Check if scripts are enqueued
		$this->assertTrue( wp_script_is( 'rsl-admin-js', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'rsl-admin-css', 'enqueued' ) );
	}

	/**
	 * Test admin scripts not enqueued on non-RSL pages
	 *
	 * @covers RSL_Admin::enqueue_admin_scripts
	 */
	public function test_admin_scripts_not_enqueued_other_pages() {
		// Mock current screen as different admin page
		set_current_screen( 'edit-post' );

		$this->admin->enqueue_admin_scripts( 'edit-post' );

		// Scripts should not be enqueued on non-RSL pages
		$this->assertFalse( wp_script_is( 'rsl-admin-js', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'rsl-admin-css', 'enqueued' ) );
	}

	/**
	 * Test menu icon generation
	 *
	 * @covers RSL_Admin::get_menu_icon
	 */
	public function test_menu_icon_generation() {
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->admin );
		$method     = $reflection->getMethod( 'get_menu_icon' );

		$icon = $method->invoke( $this->admin );

		$this->assertIsString( $icon );
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $icon );
	}

	/**
	 * Test help tabs registration
	 *
	 * @covers RSL_Admin::add_help_tabs_dashboard
	 */
	public function test_help_tabs_registration() {
		$screen     = get_current_screen();
		$screen->id = 'toplevel_page_rsl-licensing';

		$this->admin->add_help_tabs_dashboard();

		$help_tabs = $screen->get_help_tabs();

		$this->assertNotEmpty( $help_tabs );

		// Check for expected help tab IDs
		$expected_tabs = array( 'rsl-overview', 'rsl-getting-started', 'rsl-troubleshooting' );
		foreach ( $expected_tabs as $tab_id ) {
			$tab_found = false;
			foreach ( $help_tabs as $tab ) {
				if ( $tab['id'] === $tab_id ) {
					$tab_found = true;
					break;
				}
			}
			$this->assertTrue( $tab_found, "Help tab '{$tab_id}' should be registered" );
		}
	}

	/**
	 * Test Gutenberg block editor assets
	 *
	 * @covers RSL_Admin::enqueue_block_editor_assets
	 */
	public function test_block_editor_assets() {
		// Mock block editor environment
		global $current_screen;
		$current_screen = WP_Screen::get( 'post' );

		$this->admin->enqueue_block_editor_assets();

		// Check if block editor scripts are enqueued
		$this->assertTrue( wp_script_is( 'rsl-block-editor', 'enqueued' ) );
	}

	/**
	 * Test meta fields registration for Gutenberg
	 *
	 * @covers RSL_Admin::register_meta_fields
	 */
	public function test_meta_fields_registration() {
		$this->admin->register_meta_fields();

		// Verify meta field is registered
		$registered_meta = get_registered_meta_keys( 'post' );
		$this->assertArrayHasKey( '_rsl_license_id', $registered_meta );

		$meta_field = $registered_meta['_rsl_license_id'];
		$this->assertEquals( 'integer', $meta_field['type'] );
		$this->assertTrue( $meta_field['show_in_rest'] );
		$this->assertFalse( $meta_field['single'] );
	}

	/**
	 * Test capability checks
	 *
	 * @covers RSL_Admin::add_admin_menu
	 */
	public function test_capability_checks() {
		// Create user without manage_options capability
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// Admin menu should check for manage_options capability
		$menu_items = apply_filters( 'rsl_admin_menu_capability', 'manage_options' );
		$this->assertEquals( 'manage_options', $menu_items );

		// User without capability shouldn't see menu
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	/**
	 * Test admin page rendering
	 *
	 * @covers RSL_Admin::admin_page
	 */
	public function test_admin_page_rendering() {
		// Mock admin page rendering
		ob_start();
		$this->admin->admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'rsl-wp', $output );
		$this->assertStringContainsString( 'dashboard', $output );
	}

	/**
	 * Test licenses page rendering
	 *
	 * @covers RSL_Admin::licenses_page
	 */
	public function test_licenses_page_rendering() {
		// Create test licenses
		$this->create_test_license( array( 'name' => 'Page Test License 1' ) );
		$this->create_test_license( array( 'name' => 'Page Test License 2' ) );

		ob_start();
		$this->admin->licenses_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Page Test License 1', $output );
		$this->assertStringContainsString( 'Page Test License 2', $output );
	}

	/**
	 * Test add license page rendering
	 *
	 * @covers RSL_Admin::add_license_page
	 */
	public function test_add_license_page_rendering() {
		ob_start();
		$this->admin->add_license_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Add New License', $output );
		$this->assertStringContainsString( 'license-form', $output );
	}

	/**
	 * Test settings page rendering
	 *
	 * @covers RSL_Admin::settings_page
	 */
	public function test_settings_page_rendering() {
		ob_start();
		$this->admin->settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'RSL Settings', $output );
		$this->assertStringContainsString( 'rsl_global_license_id', $output );
	}

	/**
	 * Test WordPress hooks registration
	 *
	 * @covers RSL_Admin::__construct
	 */
	public function test_wordpress_hooks_registration() {
		// Verify admin hooks are registered
		$expected_hooks = array(
			'admin_menu'            => 'add_admin_menu',
			'admin_init'            => 'register_settings',
			'admin_enqueue_scripts' => 'enqueue_admin_scripts',
			'add_meta_boxes'        => 'add_meta_boxes',
			'save_post'             => 'save_post_meta',
		);

		foreach ( $expected_hooks as $hook => $method ) {
			$this->assertGreaterThan( 0, has_action( $hook ), "Hook {$hook} should be registered" );
		}

		// Verify AJAX hooks are registered
		$ajax_hooks = array(
			'wp_ajax_rsl_save_license',
			'wp_ajax_rsl_delete_license',
			'wp_ajax_rsl_generate_xml',
		);

		foreach ( $ajax_hooks as $hook ) {
			$this->assertGreaterThan( 0, has_action( $hook ), "AJAX hook {$hook} should be registered" );
		}
	}
}
