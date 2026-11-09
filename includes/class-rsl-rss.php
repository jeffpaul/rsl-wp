<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSL_RSS {

	private $license_handler;

	public function __construct() {
		$this->license_handler = new RSL_License();

		add_action( 'rss2_ns', array( $this, 'add_rsl_namespace' ) );
		add_action( 'rss2_item', array( $this, 'add_rsl_to_rss_item' ) );

		// Add custom RSS feed for RSL licenses
		add_action( 'init', array( $this, 'add_rsl_feed' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	public function add_rsl_namespace() {
		if ( ! get_option( 'rsl_enable_rss_feed', 1 ) ) {
			return;
		}

		echo 'xmlns:rsl="https://rslstandard.org/rsl"' . "\n";
	}

	public function add_rsl_to_rss_item() {
		if ( ! get_option( 'rsl_enable_rss_feed', 1 ) ) {
			return;
		}

		global $post;

		if ( ! $post ) {
			return;
		}

		$license_data = $this->get_post_license( $post );

		if ( ! $license_data ) {
			return;
		}

		// Generate RSL content for RSS item
		$this->output_rss_rsl_content( $license_data, $post );
	}

	public function add_rsl_feed() {
		add_feed( 'rsl-licenses', array( $this, 'rsl_feed_template' ) );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'rsl_feed';
		return $vars;
	}

	public function rsl_feed_template() {
		$licenses = $this->license_handler->get_licenses( array( 'active' => 1 ) );

		header( 'Content-Type: application/rss+xml; charset=UTF-8' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
		<rss xmlns:rsl="https://rslstandard.org/rsl" version="2.0">
			<channel>
				<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - RSL Licenses</title>
				<link><?php echo esc_url( home_url() ); ?></link>
				<description><?php echo esc_html( get_bloginfo( 'description' ) ); ?></description>
				<language><?php echo esc_html( get_bloginfo( 'language' ) ); ?></language>
				<lastBuildDate><?php echo esc_html( gmdate( 'r' ) ); ?></lastBuildDate>
				<generator>RSL for WordPress v<?php echo esc_html( RSL_PLUGIN_VERSION ); ?></generator>
				
				<?php foreach ( $licenses as $license ) : ?>
				<item>
					<title><?php echo esc_html( $license['name'] ); ?></title>
					<link><?php echo esc_url( $this->get_license_xml_url( $license['id'] ) ); ?></link>
					<description><?php echo esc_html( $license['description'] ? $license['description'] : 'RSL License Configuration' ); ?></description>
					<guid><?php echo esc_url( $this->get_license_xml_url( $license['id'] ) ); ?></guid>
					<?php if ( ! empty( $license['updated_at'] ) ) : ?>
					<pubDate><?php echo esc_html( gmdate( 'r', strtotime( $license['updated_at'] ) ) ); ?></pubDate>
					<?php endif; ?>
					
					<?php $this->output_rss_rsl_content( $license ); ?>
				</item>
				<?php endforeach; ?>
				
			</channel>
		</rss>
		<?php
		exit;
	}

	private function get_post_license( $post ) {
		// Check for post-specific license
		$post_license_id = get_post_meta( $post->ID, '_rsl_license_id', true );
		if ( $post_license_id ) {
			$license_data = $this->license_handler->get_license( $post_license_id );
			if ( $license_data && $license_data['active'] ) {
				return $this->prepare_post_license_data( $license_data, $post );
			}
		}

		// Fall back to global license
		$global_license_id = get_option( 'rsl_global_license_id', 0 );
		if ( $global_license_id > 0 ) {
			$license_data = $this->license_handler->get_license( $global_license_id );
			if ( $license_data && $license_data['active'] ) {
				return $this->prepare_post_license_data( $license_data, $post );
			}
		}

		return null;
	}

	private function prepare_post_license_data( $license_data, $post = null ) {
		if ( $post ) {
			// Override content URL for specific post
			$override_url = get_post_meta( $post->ID, '_rsl_override_content_url', true );
			if ( ! empty( $override_url ) ) {
				$license_data['content_url'] = $override_url;
			} else {
				$license_data['content_url'] = get_permalink( $post->ID );
			}
		}

		return $license_data;
	}

	private function output_rss_rsl_content( $license_data, $post = null ) {
		if ( $post ) {
			// A feed item describes one post, so anchor its RSL block to that item.
			$license_data['content_url'] = get_permalink( $post->ID );
		}

		$xml = $this->license_handler->generate_rsl_xml(
			$license_data,
			array(
				'standalone' => false,
				'root'       => false,
				'prefix'     => 'rsl:',
			)
		);

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generator escapes values.
	}

	private function get_license_xml_url( $license_id ) {
		return RSL_Server::get_license_url( $license_id );
	}

	public function get_rsl_feed_url() {
		return RSL_Server::get_feed_url();
	}


}