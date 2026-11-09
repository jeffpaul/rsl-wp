<?php
/**
 * Tests for RSL_RSS class.
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_License;
use RSL_RSS;

/**
 * Test RSL_RSS functionality.
 *
 * @group unit
 * @group rss
 */
class TestRSLRSS extends TestCase {

	/**
	 * RSS instance under test.
	 *
	 * @var RSL_RSS
	 */
	private $rss;

	/**
	 * Test license handler capturing the data passed to the serializer.
	 *
	 * @var RSL_License
	 */
	private $license_handler;

	public function setUp(): void {
		parent::setUp();

		$this->rss             = new RSL_RSS();
		$this->license_handler = new class() extends RSL_License {
			public $license_data;
			public $serialized_data;
			public $serializer_options;

			public function get_license( $license_id ) {
				return $this->license_data;
			}

			public function generate_rsl_xml( $license_data, $options = array() ) {
				$this->serialized_data    = $license_data;
				$this->serializer_options = $options;
				return '<rsl:content url="' . esc_attr( $license_data['content_url'] ) . '"><rsl:license/></rsl:content>';
			}
		};

		$property = new \ReflectionProperty( RSL_RSS::class, 'license_handler' );
		$property->setValue( $this->rss, $this->license_handler );

		$this->license_handler->license_data = array(
			'id'          => 10,
			'name'        => 'RSS test license',
			'content_url' => '/site-default',
			'active'      => 1,
		);
	}

	/**
	 * A same-origin per-post content URL override is retained in the RSS item.
	 *
	 * @covers RSL_RSS::add_rsl_to_rss_item
	 * @covers RSL_RSS::output_rss_rsl_content
	 * @covers RSL_RSS::get_post_content_url_for_rss
	 */
	public function test_rss_item_uses_same_origin_content_url_override() {
		update_option( 'rsl_enable_rss_feed', 1 );
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		update_post_meta( 42, '_rsl_license_id', 10 );
		update_post_meta( 42, '_rsl_override_content_url', 'http://example.org/licensed-content/' );

		ob_start();
		$this->rss->add_rsl_to_rss_item();
		$output = ob_get_clean();

		$this->assertSame( 'http://example.org/licensed-content/', $this->license_handler->serialized_data['content_url'] );
		$this->assertSame( 'rsl:', $this->license_handler->serializer_options['prefix'] );
		$this->assertStringContainsString( '<rsl:content', $output );
	}

	/**
	 * Cross-origin overrides are not emitted in RSS association blocks.
	 *
	 * @covers RSL_RSS::add_rsl_to_rss_item
	 * @covers RSL_RSS::output_rss_rsl_content
	 * @covers RSL_RSS::get_post_content_url_for_rss
	 */
	public function test_rss_item_falls_back_to_permalink_for_cross_origin_override() {
		update_option( 'rsl_enable_rss_feed', 1 );
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		update_post_meta( 42, '_rsl_license_id', 10 );
		update_post_meta( 42, '_rsl_override_content_url', 'https://other.example/licensed-content/' );

		ob_start();
		$this->rss->add_rsl_to_rss_item();
		ob_end_clean();

		$this->assertSame( 'http://example.org/?p=42', $this->license_handler->serialized_data['content_url'] );
	}

	/**
	 * Disabled RSS integration emits no item-level RSL output.
	 *
	 * @covers RSL_RSS::add_rsl_to_rss_item
	 */
	public function test_disabled_rss_integration_skips_item_output() {
		update_option( 'rsl_enable_rss_feed', 0 );
		$GLOBALS['post'] = (object) array( 'ID' => 42 );

		ob_start();
		$this->rss->add_rsl_to_rss_item();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertNull( $this->license_handler->serialized_data );
	}

	/**
	 * Disabled RSS integration does not render the dedicated license feed.
	 *
	 * @covers RSL_RSS::rsl_feed_template
	 */
	public function test_disabled_rss_integration_skips_dedicated_feed() {
		update_option( 'rsl_enable_rss_feed', 0 );

		ob_start();
		$this->rss->rsl_feed_template();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( 404, $GLOBALS['_rsl_test_status'] );
		unset( $GLOBALS['_rsl_test_status'] );
	}
}
