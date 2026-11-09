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
		if ( ! get_option( 'rsl_enable_rss_feed', 1 ) ) {
			return;
		}

		add_feed( 'rsl-licenses', array( $this, 'rsl_feed_template' ) );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'rsl_feed';
		return $vars;
	}

	public function rsl_feed_template() {
		if ( ! get_option( 'rsl_enable_rss_feed', 1 ) ) {
			status_header( 404 );
			return;
		}

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
			$license_data['content_url'] = $this->get_post_content_url_for_rss( $license_data, $post );
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

	/**
	 * Use post-specific content URL overrides only when they remain on this site's origin.
	 *
	 * @param array  $license_data License data prepared for this post.
	 * @param object $post         Feed post.
	 * @return string Same-origin content URL.
	 */
	private function get_post_content_url_for_rss( $license_data, $post ) {
		$content_url = isset( $license_data['content_url'] ) ? trim( $license_data['content_url'] ) : '';
		$permalink   = get_permalink( $post->ID );

		if ( '' === $content_url ) {
			return $permalink;
		}

		$site_origin    = wp_parse_url( home_url( '/' ) );
		$content_origin = wp_parse_url( $content_url );

		if ( ! is_array( $site_origin ) || ! is_array( $content_origin ) ) {
			return $permalink;
		}

		// Root-relative URLs are same-origin; other relative forms can resolve against a feed path.
		if ( ! isset( $content_origin['host'] ) && ! isset( $content_origin['scheme'] ) ) {
			return 0 === strpos( $content_url, '/' ) && 0 !== strpos( $content_url, '//' )
				? $content_url
				: $permalink;
		}

		$site_scheme    = strtolower( $site_origin['scheme'] ?? '' );
		$content_scheme = strtolower( $content_origin['scheme'] ?? $site_scheme );
		$site_host      = strtolower( $site_origin['host'] ?? '' );
		$content_host   = strtolower( $content_origin['host'] ?? '' );
		$site_port      = $site_origin['port'] ?? ( 'https' === $site_scheme ? 443 : 80 );
		$content_port   = $content_origin['port'] ?? ( 'https' === $content_scheme ? 443 : 80 );

		if ( $site_scheme !== $content_scheme || $site_host !== $content_host || $site_port !== $content_port ) {
			return $permalink;
		}

		return $content_url;
	}

	private function get_license_xml_url( $license_id ) {
		return RSL_Server::get_license_url( $license_id );
	}

	public function get_rsl_feed_url() {
		return RSL_Server::get_feed_url();
	}
}