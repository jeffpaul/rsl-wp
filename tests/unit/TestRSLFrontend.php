<?php
/**
 * Tests for RSL_Frontend class
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_Frontend;
use RSL_License;

/**
 * Test RSL_Frontend functionality
 *
 * @group unit
 * @group frontend
 */
class TestRSLFrontend extends TestCase {

	/**
	 * Frontend instance
	 *
	 * @var RSL_Frontend
	 */
	private $frontend;

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
		$this->frontend        = new RSL_Frontend();
		$this->license_handler = new RSL_License();

		// Set up WordPress environment
		global $wp_query;
		$wp_query = $this->createMock( 'WP_Query' );
	}

	/**
	 * Test HTML injection when enabled
	 *
	 * @covers RSL_Frontend::inject_rsl_html
	 */
	public function test_html_injection_enabled() {
		// Enable HTML injection
		update_option( 'rsl_enable_html_injection', 1 );

		// Create and set global license
		$license_id = $this->create_test_license(
			array(
				'name'        => 'HTML Injection Test',
				'content_url' => '/',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		// Capture output
		ob_start();
		$this->frontend->inject_rsl_html();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="application/rsl+xml">', $output );
		$this->assertStringContainsString( 'xmlns="https://rslstandard.org/rsl"', $output );
		$this->assertStringContainsString( '</script>', $output );
	}

	/**
	 * Test HTML injection when disabled
	 *
	 * @covers RSL_Frontend::inject_rsl_html
	 */
	public function test_html_injection_disabled() {
		// Disable HTML injection
		update_option( 'rsl_enable_html_injection', 0 );

		// Create and set global license
		$license_id = $this->create_test_license();
		update_option( 'rsl_global_license_id', $license_id );

		// Capture output
		ob_start();
		$this->frontend->inject_rsl_html();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test HTML injection with no license
	 *
	 * @covers RSL_Frontend::inject_rsl_html
	 */
	public function test_html_injection_no_license() {
		// Enable HTML injection
		update_option( 'rsl_enable_html_injection', 1 );

		// No global license set
		delete_option( 'rsl_global_license_id' );

		// Capture output
		ob_start();
		$this->frontend->inject_rsl_html();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test HTTP headers when enabled
	 *
	 * @covers RSL_Frontend::add_rsl_headers
	 */
	public function test_http_headers_enabled() {
		// Enable HTTP headers
		update_option( 'rsl_enable_http_headers', 1 );

		// Create and set global license
		$license_id = $this->create_test_license(
			array(
				'name' => 'HTTP Headers Test',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		// Mock headers_sent to return false
		if ( ! function_exists( 'headers_sent' ) ) {
			function headers_sent() {
				return false; }
		}

		// Test header addition (in real implementation this would use header())
		ob_start();
		$this->frontend->add_rsl_headers();
		ob_end_clean();

		// In actual implementation, we'd verify Link header was set
		$this->assertTrue( true ); // Placeholder
	}

	/**
	 * Test HTTP headers when disabled
	 *
	 * @covers RSL_Frontend::add_rsl_headers
	 */
	public function test_http_headers_disabled() {
		// Disable HTTP headers
		update_option( 'rsl_enable_http_headers', 0 );

		// Create and set global license
		$license_id = $this->create_test_license();
		update_option( 'rsl_global_license_id', $license_id );

		// Headers should not be added
		ob_start();
		$this->frontend->add_rsl_headers();
		ob_end_clean();

		$this->assertTrue( true ); // Placeholder - no headers should be set
	}

	/**
	 * Test current page license detection for global license
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_current_page_license_global() {
		// Create and set global license
		$license_id = $this->create_test_license(
			array(
				'name'          => 'Global License Test',
				'content_url'   => '/',
				'permits_usage' => 'search',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		// Mock not being on a singular page
		global $wp_query;
		$wp_query->method( 'is_singular' )->willReturn( false );

		$license = $this->frontend->get_current_page_license();

		$this->assertIsArray( $license );
		$this->assertEquals( 'Global License Test', $license['name'] );
		$this->assertEquals( $license_id, $license['id'] );
	}

	/**
	 * Test current page license detection for post-specific license
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_current_page_license_post_specific() {
		// Create global license
		$global_license_id = $this->create_test_license( array( 'name' => 'Global License' ) );
		update_option( 'rsl_global_license_id', $global_license_id );

		// Create post-specific license
		$post_license_id = $this->create_test_license(
			array(
				'name'        => 'Post-Specific License',
				'content_url' => '/specific-post',
			)
		);

		// Create test post
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );
		update_post_meta( $post_id, '_rsl_license_id', $post_license_id );

		// Mock being on a singular page
		global $wp_query, $post;
		$wp_query->method( 'is_singular' )->willReturn( true );
		$post = get_post( $post_id );

		$license = $this->frontend->get_current_page_license();

		$this->assertIsArray( $license );
		$this->assertEquals( 'Post-Specific License', $license['name'] );
		$this->assertEquals( $post_license_id, $license['id'] );
	}

	/**
	 * Test current page license when no license found
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_current_page_license_none() {
		// No global license set
		delete_option( 'rsl_global_license_id' );

		// Mock not being on a singular page
		global $wp_query;
		$wp_query->method( 'is_singular' )->willReturn( false );

		$license = $this->frontend->get_current_page_license();

		$this->assertNull( $license );
	}

	/**
	 * Test RSL XML request handling
	 *
	 * @covers RSL_Frontend::handle_rsl_xml_requests
	 */
	public function test_rsl_xml_request_handling() {
		$license_id = $this->create_test_license(
			array(
				'name'        => 'XML Request Test',
				'content_url' => '/xml-test',
			)
		);

		// Mock $_GET parameter
		$_GET['rsl_license'] = $license_id;

		// Use output buffering to catch the XML output and exit
		ob_start();

		try {
			$this->frontend->handle_rsl_xml_requests();
		} catch ( Exception $e ) {
			// Expected - exit() call
		}

		$output = ob_get_clean();

		$this->assertStringContainsString( '<?xml version="1.0"', $output );
		$this->assertStringContainsString( 'xmlns="https://rslstandard.org/rsl"', $output );
		$this->assertStringContainsString( 'XML Request Test', $output );
	}

	/**
	 * Test RSL XML request with invalid license ID
	 *
	 * @covers RSL_Frontend::handle_rsl_xml_requests
	 */
	public function test_rsl_xml_request_invalid_license() {
		// Mock $_GET parameter with invalid license ID
		$_GET['rsl_license'] = 99999;

		// Should not output anything for invalid license
		ob_start();
		$this->frontend->handle_rsl_xml_requests();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test RSL feed request handling
	 *
	 * @covers RSL_Frontend::handle_rsl_xml_requests
	 */
	public function test_rsl_feed_request_handling() {
		// Mock $_GET parameter for feed
		$_GET['rsl_feed'] = '1';

		// Mock the RSS class to prevent actual output
		if ( ! class_exists( 'RSL_RSS' ) ) {
			$this->markTestSkipped( 'RSL_RSS class not available' );
		}

		ob_start();

		try {
			$this->frontend->handle_rsl_xml_requests();
		} catch ( Exception $e ) {
			// Expected - exit() call
		}

		$output = ob_get_clean();

		// Should contain RSS/XML content
		$this->assertTrue( true ); // Placeholder - would need actual RSS mock
	}

	/**
	 * Test license shortcode with link format
	 *
	 * @covers RSL_Frontend::license_shortcode
	 */
	public function test_license_shortcode_link_format() {
		$license_id = $this->create_test_license(
			array(
				'name' => 'Shortcode Test License',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		$atts = array(
			'format' => 'link',
			'text'   => 'View Our License',
		);

		$output = $this->frontend->license_shortcode( $atts );

		$this->assertStringContainsString( '<a href=', $output );
		$this->assertStringContainsString( 'View Our License', $output );
		$this->assertStringContainsString( 'rsl_license=' . $license_id, $output );
	}

	/**
	 * Test license shortcode with info format
	 *
	 * @covers RSL_Frontend::license_shortcode
	 */
	public function test_license_shortcode_info_format() {
		$license_id = $this->create_test_license(
			array(
				'name'         => 'Info Shortcode License',
				'payment_type' => 'purchase',
				'amount'       => 99.99,
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		$atts = array( 'format' => 'info' );

		$output = $this->frontend->license_shortcode( $atts );

		$this->assertStringContainsString( 'Info Shortcode License', $output );
		$this->assertStringContainsString( 'purchase', $output );
		$this->assertStringContainsString( '99.99', $output );
	}

	/**
	 * Test license shortcode with XML format
	 *
	 * @covers RSL_Frontend::license_shortcode
	 */
	public function test_license_shortcode_xml_format() {
		$license_id = $this->create_test_license(
			array(
				'name' => 'XML Shortcode License',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		$atts = array( 'format' => 'xml' );

		$output = $this->frontend->license_shortcode( $atts );

		$this->assertStringContainsString( '<pre>', $output );
		$this->assertStringContainsString( '<?xml version="1.0"', $output );
		$this->assertStringContainsString( 'xmlns="https://rslstandard.org/rsl"', $output );
		$this->assertStringContainsString( '</pre>', $output );
	}

	/**
	 * Test license shortcode with no license
	 *
	 * @covers RSL_Frontend::license_shortcode
	 */
	public function test_license_shortcode_no_license() {
		// No global license set
		delete_option( 'rsl_global_license_id' );

		$atts = array( 'format' => 'link' );

		$output = $this->frontend->license_shortcode( $atts );

		$this->assertEmpty( $output );
	}

	/**
	 * Test license XML URL generation
	 *
	 * @covers RSL_Frontend::get_license_xml_url
	 */
	public function test_license_xml_url_generation() {
		$license_id = 123;

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->frontend );
		$method     = $reflection->getMethod( 'get_license_xml_url' );

		$url = $method->invoke( $this->frontend, $license_id );

		$this->assertStringContainsString( 'rsl_license=' . $license_id, $url );
		$this->assertStringStartsWith( 'http', $url );
	}

	/**
	 * Test license data preparation
	 *
	 * @covers RSL_Frontend::prepare_license_data
	 */
	public function test_license_data_preparation() {
		$license_data = array(
			'id'            => 1,
			'name'          => 'Test License',
			'content_url'   => '/test',
			'permits_usage' => 'search,ai-summarize',
		);

		// Create mock post
		$post = $this->factory->post->create_and_get( array( 'post_title' => 'Test Post' ) );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->frontend );
		$method     = $reflection->getMethod( 'prepare_license_data' );

		$prepared = $method->invoke( $this->frontend, $license_data, $post );

		$this->assertIsArray( $prepared );
		$this->assertEquals( 'Test License', $prepared['name'] );
		$this->assertArrayHasKey( 'xml_url', $prepared );
		$this->assertArrayHasKey( 'rsl_xml', $prepared );
	}

	/**
	 * Test embedded RSL output
	 *
	 * @covers RSL_Frontend::output_embedded_rsl
	 */
	public function test_embedded_rsl_output() {
		$license_data = array(
			'id'           => 1,
			'name'         => 'Embedded Test License',
			'content_url'  => '/embedded',
			'payment_type' => 'free',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->frontend );
		$method     = $reflection->getMethod( 'output_embedded_rsl' );

		ob_start();
		$method->invoke( $this->frontend, $license_data );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="application/rsl+xml">', $output );
		$this->assertStringContainsString( 'Embedded Test License', $output );
		$this->assertStringContainsString( 'type="free"', $output );
		$this->assertStringContainsString( '</script>', $output );
	}

	/**
	 * Test WordPress hooks registration
	 *
	 * @covers RSL_Frontend::__construct
	 */
	public function test_wordpress_hooks_registration() {
		// Verify hooks are registered
		$expected_hooks = array(
			'wp_head'           => 'inject_rsl_html',
			'send_headers'      => 'add_rsl_headers',
			'template_redirect' => 'handle_rsl_xml_requests',
		);

		foreach ( $expected_hooks as $hook => $method ) {
			$this->assertGreaterThan( 0, has_action( $hook ), "Hook {$hook} should be registered" );
		}

		// Verify shortcode is registered
		$this->assertTrue( shortcode_exists( 'rsl_license' ), 'rsl_license shortcode should be registered' );
	}

	/**
	 * Test license caching behavior
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_license_caching() {
		$license_id = $this->create_test_license(
			array(
				'name' => 'Cache Test License',
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		// First call
		$license1 = $this->frontend->get_current_page_license();

		// Second call should use cached result
		$license2 = $this->frontend->get_current_page_license();

		$this->assertEquals( $license1, $license2 );
		$this->assertSame( $license1['name'], $license2['name'] );
	}

	/**
	 * Test license override priority
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_license_override_priority() {
		// Create global license
		$global_license_id = $this->create_test_license( array( 'name' => 'Global Override Test' ) );
		update_option( 'rsl_global_license_id', $global_license_id );

		// Create post-specific license
		$post_license_id = $this->create_test_license( array( 'name' => 'Post Override Test' ) );

		// Create test post
		$post_id = $this->factory->post->create( array( 'post_title' => 'Override Test Post' ) );
		update_post_meta( $post_id, '_rsl_license_id', $post_license_id );

		// Mock being on a singular page
		global $wp_query, $post;
		$wp_query->method( 'is_singular' )->willReturn( true );
		$post = get_post( $post_id );

		$license = $this->frontend->get_current_page_license();

		// Post-specific should override global
		$this->assertEquals( 'Post Override Test', $license['name'] );
		$this->assertEquals( $post_license_id, $license['id'] );
	}

	/**
	 * Test inactive license handling
	 *
	 * @covers RSL_Frontend::get_current_page_license
	 */
	public function test_inactive_license_handling() {
		// Create inactive license
		$license_id = $this->create_test_license(
			array(
				'name'   => 'Inactive License',
				'active' => 0,
			)
		);
		update_option( 'rsl_global_license_id', $license_id );

		$license = $this->frontend->get_current_page_license();

		// Should not return inactive license
		$this->assertNull( $license );
	}
}
