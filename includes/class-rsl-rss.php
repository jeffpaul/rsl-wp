<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSL_RSS {

	private $license_handler;

	public function __construct() {
		$this->license_handler = new RSL_License();

		add_action( 'rss2_ns', array( $this, 'add_rsl_namespace' ) );

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
		// Generate content URL
		$content_url = $license_data['content_url'];

		if ( empty( $content_url ) && $post ) {
			$content_url = get_permalink( $post->ID );
		} elseif ( empty( $content_url ) ) {
			$content_url = home_url( '/' );
		}

		echo "\n    <rsl:content url=\"" . esc_attr( $content_url ) . '"';

		if ( ! empty( $license_data['server_url'] ) ) {
			echo ' server="' . esc_attr( $license_data['server_url'] ) . '"';
		}

		if ( ! empty( $license_data['encrypted'] ) && $license_data['encrypted'] == 1 ) {
			echo ' encrypted="true"';
		}

		if ( ! empty( $license_data['lastmod'] ) ) {
			echo ' lastmod="' . esc_attr( gmdate( 'c', strtotime( $license_data['lastmod'] ) ) ) . '"';
		}

		echo ">\n";

		// Schema information
		if ( ! empty( $license_data['schema_url'] ) ) {
			echo '      <rsl:schema>' . esc_html( $license_data['schema_url'] ) . "</rsl:schema>\n";
		}

		// Copyright information
		if ( ! empty( $license_data['copyright_holder'] ) ) {
			echo '      <rsl:copyright';
			if ( ! empty( $license_data['copyright_type'] ) ) {
				echo ' type="' . esc_attr( $license_data['copyright_type'] ) . '"';
			}
			if ( ! empty( $license_data['contact_email'] ) ) {
				echo ' contactEmail="' . esc_attr( $license_data['contact_email'] ) . '"';
			}
			if ( ! empty( $license_data['contact_url'] ) ) {
				echo ' contactUrl="' . esc_attr( $license_data['contact_url'] ) . '"';
			}
			echo '>' . esc_html( $license_data['copyright_holder'] ) . "</rsl:copyright>\n";
		}

		// Terms URL
		if ( ! empty( $license_data['terms_url'] ) ) {
			echo '      <rsl:terms>' . esc_html( $license_data['terms_url'] ) . "</rsl:terms>\n";
		}

		// License information
		echo "      <rsl:license>\n";

		// Permits
		if ( ! empty( $license_data['permits_usage'] ) ) {
			echo '        <rsl:permits type="usage">' . esc_html( $license_data['permits_usage'] ) . "</rsl:permits>\n";
		}

		if ( ! empty( $license_data['permits_user'] ) ) {
			echo '        <rsl:permits type="user">' . esc_html( $license_data['permits_user'] ) . "</rsl:permits>\n";
		}

		if ( ! empty( $license_data['permits_geo'] ) ) {
			echo '        <rsl:permits type="geo">' . esc_html( $license_data['permits_geo'] ) . "</rsl:permits>\n";
		}

		// Prohibits
		if ( ! empty( $license_data['prohibits_usage'] ) ) {
			echo '        <rsl:prohibits type="usage">' . esc_html( $license_data['prohibits_usage'] ) . "</rsl:prohibits>\n";
		}

		if ( ! empty( $license_data['prohibits_user'] ) ) {
			echo '        <rsl:prohibits type="user">' . esc_html( $license_data['prohibits_user'] ) . "</rsl:prohibits>\n";
		}

		if ( ! empty( $license_data['prohibits_geo'] ) ) {
			echo '        <rsl:prohibits type="geo">' . esc_html( $license_data['prohibits_geo'] ) . "</rsl:prohibits>\n";
		}

		// Payment information
		if ( ! empty( $license_data['payment_type'] ) && $license_data['payment_type'] !== 'free' ) {
			echo '        <rsl:payment type="' . esc_attr( $license_data['payment_type'] ) . "\">\n";

			if ( ! empty( $license_data['standard_url'] ) ) {
				echo '          <rsl:standard>' . esc_html( $license_data['standard_url'] ) . "</rsl:standard>\n";
			}

			if ( ! empty( $license_data['custom_url'] ) ) {
				echo '          <rsl:custom>' . esc_html( $license_data['custom_url'] ) . "</rsl:custom>\n";
			}

			if ( ! empty( $license_data['amount'] ) && $license_data['amount'] > 0 ) {
				echo '          <rsl:amount currency="' . esc_attr( $license_data['currency'] ) . '">' .
					esc_html( $license_data['amount'] ) . "</rsl:amount>\n";
			}

			echo "        </rsl:payment>\n";
		} else {
			echo "        <rsl:payment type=\"free\"/>\n";
		}

		// Legal information
		if ( ! empty( $license_data['warranty'] ) ) {
			echo '        <rsl:legal type="warranty">' . esc_html( $license_data['warranty'] ) . "</rsl:legal>\n";
		}

		if ( ! empty( $license_data['disclaimer'] ) ) {
			echo '        <rsl:legal type="disclaimer">' . esc_html( $license_data['disclaimer'] ) . "</rsl:legal>\n";
		}

		echo "      </rsl:license>\n";
		echo "    </rsl:content>\n";
	}

	private function get_license_xml_url( $license_id ) {
		return add_query_arg( 'rsl_license', $license_id, home_url() );
	}

	public function get_rsl_feed_url() {
		return home_url( 'feed/rsl-licenses/' );
	}

	public function enhance_existing_feeds() {
		// This method can be called to enhance existing RSS feeds with RSL data
		// It's automatically hooked into WordPress RSS generation

		if ( ! get_option( 'rsl_enable_rss_feed', 1 ) ) {
			return;
		}

		// Add RSL metadata to standard WordPress feeds
		add_action(
			'rss_head',
			function () {
				echo "<!-- Enhanced with RSL Licensing -->\n";
			}
		);

		add_action(
			'rss2_head',
			function () {
				echo "<!-- Enhanced with RSL Licensing -->\n";

				// Add global license information to feed header if available
				$global_license_id = get_option( 'rsl_global_license_id', 0 );
				if ( $global_license_id > 0 ) {
					$license_data = $this->license_handler->get_license( $global_license_id );
					if ( $license_data && $license_data['active'] ) {
						$xml_url = $this->get_license_xml_url( $global_license_id );
						echo '<license>' . esc_url( $xml_url ) . "</license>\n";
					}
				}
			}
		);
	}
}