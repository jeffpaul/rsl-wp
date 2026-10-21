<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSL_Frontend {

	private $license_handler;

	public function __construct() {
		$this->license_handler = new RSL_License();

		add_action( 'wp_head', array( $this, 'inject_rsl_html' ) );
		add_action( 'send_headers', array( $this, 'add_rsl_headers' ) );
		add_action( 'template_redirect', array( $this, 'handle_rsl_xml_requests' ) );

		add_shortcode( 'rsl_license', array( $this, 'license_shortcode' ) );
	}

	public function inject_rsl_html() {
		if ( ! get_option( 'rsl_enable_html_injection', 1 ) ) {
			return;
		}

		$license_data = $this->get_current_page_license();

		if ( ! $license_data ) {
			return;
		}

		$this->output_embedded_rsl( $license_data );
	}

	public function add_rsl_headers() {
		if ( ! get_option( 'rsl_enable_http_headers', 1 ) ) {
			return;
		}

		$license_data = $this->get_current_page_license();

		if ( ! $license_data ) {
			return;
		}

		$xml_url = $this->get_license_xml_url( $license_data['id'] );

		if ( $xml_url ) {
			header( 'Link: <' . $xml_url . '>; rel="license"; type="application/rsl+xml"' );
		}
	}

	public function handle_rsl_xml_requests() {
		global $wp_query;

		if ( isset( $_GET['rsl_license'] ) && is_numeric( $_GET['rsl_license'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'rsl_license_' . intval( $_GET['rsl_license'] ) ) ) {
			$license_id   = intval( $_GET['rsl_license'] );
			$license_data = $this->license_handler->get_license( $license_id );

			if ( $license_data && $license_data['active'] ) {
				header( 'Content-Type: application/rsl+xml; charset=UTF-8' );
				header( 'Cache-Control: public, max-age=3600' );

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->license_handler->generate_rsl_xml( $license_data );
				exit;
			}
		}

		if ( isset( $_GET['rsl_feed'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'rsl_feed' ) ) {
			$this->output_rsl_feed();
			exit;
		}
	}

	public function get_current_page_license() {
		global $post;

		// Check for post-specific license first
		if ( is_singular() && $post ) {
			$post_license_id = get_post_meta( $post->ID, '_rsl_license_id', true );
			if ( $post_license_id ) {
				$license_data = $this->license_handler->get_license( $post_license_id );
				if ( $license_data && $license_data['active'] ) {
					return $this->prepare_license_data( $license_data, $post );
				}
			}
		}

		// Fall back to global license
		$global_license_id = get_option( 'rsl_global_license_id', 0 );
		if ( $global_license_id > 0 ) {
			$license_data = $this->license_handler->get_license( $global_license_id );
			if ( $license_data && $license_data['active'] ) {
				return $this->prepare_license_data( $license_data );
			}
		}

		return null;
	}

	private function prepare_license_data( $license_data, $post = null ) {
		// Override content URL if specified for post
		if ( $post ) {
			$override_url = get_post_meta( $post->ID, '_rsl_override_content_url', true );
			if ( ! empty( $override_url ) ) {
				$license_data['content_url'] = $override_url;
			} elseif ( empty( $license_data['content_url'] ) || $license_data['content_url'] === '/' ) {
				// Use current page URL if content_url is empty or "/"
				$license_data['content_url'] = get_permalink( $post->ID );
			}
		} elseif ( empty( $license_data['content_url'] ) ) {
			// For non-post pages, use current URL if content_url is empty
			$request_uri                 = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$license_data['content_url'] = home_url( esc_url_raw( $request_uri ) );
		} elseif ( $license_data['content_url'] === '/' ) {
			$license_data['content_url'] = home_url( '/' );
		}

		return $license_data;
	}

	private function output_embedded_rsl( $license_data ) {
		$namespace = get_option( 'rsl_default_namespace', 'https://rslstandard.org/rsl' );

		echo "\n<!-- RSL Licensing Information -->\n";
		echo '<script type="application/rsl+xml">' . "\n";

		$xml = $this->license_handler->generate_rsl_xml(
			$license_data,
			array(
				'namespace'  => $namespace,
				'standalone' => false,
			)
		);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml . "\n";
		echo '</script>' . "\n";
		echo "<!-- End RSL Licensing Information -->\n\n";
	}

	private function get_license_xml_url( $license_id ) {
		$base_url = trailingslashit( home_url() );
		return add_query_arg( 'rsl_license', $license_id, $base_url );
	}

	private function output_rsl_feed() {
		$licenses = $this->license_handler->get_licenses( array( 'active' => 1 ) );

		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=1800' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss xmlns:rsl="https://rslstandard.org/rsl" version="2.0">' . "\n";
		echo '  <channel>' . "\n";
		echo '    <title>' . esc_html( get_bloginfo( 'name' ) ) . ' - RSL Licenses</title>' . "\n";
		echo '    <link>' . esc_url( home_url() ) . '</link>' . "\n";
		echo '    <description>' . esc_html( get_bloginfo( 'description' ) ) . '</description>' . "\n";
		echo '    <language>' . esc_html( get_bloginfo( 'language' ) ) . '</language>' . "\n";
		echo '    <lastBuildDate>' . esc_html( gmdate( 'r' ) ) . '</lastBuildDate>' . "\n";

		foreach ( $licenses as $license ) {
			echo '    <item>' . "\n";
			echo '      <title>' . esc_html( $license['name'] ) . '</title>' . "\n";
			echo '      <link>' . esc_url( $this->get_license_xml_url( $license['id'] ) ) . '</link>' . "\n";
			echo '      <description>' . esc_html( $license['description'] ? $license['description'] : 'RSL License' ) . '</description>' . "\n";
			echo '      <guid>' . esc_url( $this->get_license_xml_url( $license['id'] ) ) . '</guid>' . "\n";

			if ( ! empty( $license['updated_at'] ) ) {
				echo '      <pubDate>' . esc_html( gmdate( 'r', strtotime( $license['updated_at'] ) ) ) . '</pubDate>' . "\n";
			}

			// Add RSL content as RSS extension
			$rsl_xml = $this->license_handler->generate_rsl_xml(
				$license,
				array(
					'namespace'  => 'https://rslstandard.org/rsl',
					'standalone' => false,
				)
			);

			// Remove the opening rsl tag and add proper namespace prefixes
			$rsl_xml = str_replace( '<rsl xmlns="https://rslstandard.org/rsl">', '', $rsl_xml );
			$rsl_xml = str_replace( '</rsl>', '', $rsl_xml );
			$rsl_xml = preg_replace( '/<(\/?)(content|license|permits|prohibits|payment|standard|custom|amount|legal|schema|copyright|terms)/', '<$1rsl:$2', $rsl_xml );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $rsl_xml;
			echo '    </item>' . "\n";
		}

		echo '  </channel>' . "\n";
		echo '</rss>' . "\n";
	}

	public function license_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'format' => 'link', // link, xml, info
				'text'   => __( 'View License', 'rsl-wp' ),
			),
			$atts,
			'rsl_license'
		);

		$license_id = intval( $atts['id'] );

		if ( $license_id <= 0 ) {
			// Use current page license
			$license_data = $this->get_current_page_license();
			if ( ! $license_data ) {
				return '';
			}
			$license_id = $license_data['id'];
		} else {
			$license_data = $this->license_handler->get_license( $license_id );
		}

		if ( ! $license_data || ! $license_data['active'] ) {
			return '';
		}

		switch ( $atts['format'] ) {
			case 'xml':
				return '<pre><code>' . esc_html( $this->license_handler->generate_rsl_xml( $license_data ) ) . '</code></pre>';

			case 'info':
				$output  = '<div class="rsl-license-info">';
				$output .= '<h4>' . esc_html( $license_data['name'] ) . '</h4>';

				if ( ! empty( $license_data['description'] ) ) {
					$output .= '<p>' . esc_html( $license_data['description'] ) . '</p>';
				}

				$output .= '<ul>';
				$output .= '<li><strong>' . __( 'Payment Type:', 'rsl-wp' ) . '</strong> ' .
							esc_html( ucfirst( str_replace( '-', ' ', $license_data['payment_type'] ) ) ) . '</li>';

				if ( ! empty( $license_data['permits_usage'] ) ) {
					$output .= '<li><strong>' . __( 'Permitted Usage:', 'rsl-wp' ) . '</strong> ' .
								esc_html( $license_data['permits_usage'] ) . '</li>';
				}

				if ( ! empty( $license_data['copyright_holder'] ) ) {
					$output .= '<li><strong>' . __( 'Copyright:', 'rsl-wp' ) . '</strong> ' .
								esc_html( $license_data['copyright_holder'] ) . '</li>';
				}

				$output .= '</ul>';
				$output .= '</div>';

				return $output;

			case 'link':
			default:
				$xml_url = $this->get_license_xml_url( $license_id );
				return '<a href="' . esc_url( $xml_url ) . '" class="rsl-license-link" target="_blank">' .
						esc_html( $atts['text'] ) . '</a>';
		}
	}

	public function get_rsl_feed_url() {
		return add_query_arg( 'rsl_feed', '1', home_url() );
	}
}
