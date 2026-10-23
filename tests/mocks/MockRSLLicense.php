<?php
/**
 * Mock RSL_License class for testing
 */

if ( ! class_exists( 'RSL_License' ) ) {
	class RSL_License {
		private static $licenses   = array();
		private static $id_counter = 1;

		public function create_license( $data ) {
			$defaults = array(
				'name'             => '',
				'description'      => '',
				'content_url'      => '',
				'server_url'       => '',
				'encrypted'        => 0,
				'lastmod'          => date( 'Y-m-d H:i:s' ),
				'permits_usage'    => '',
				'permits_user'     => '',
				'permits_geo'      => '',
				'prohibits_usage'  => '',
				'prohibits_user'   => '',
				'prohibits_geo'    => '',
				'payment_type'     => 'free',
				'standard_url'     => '',
				'custom_url'       => '',
				'amount'           => 0,
				'currency'         => 'USD',
				'warranty'         => '',
				'disclaimer'       => '',
				'schema_url'       => '',
				'copyright_holder' => '',
				'copyright_type'   => '',
				'contact_email'    => '',
				'contact_url'      => '',
				'terms_url'        => '',
				'active'           => 1,
			);

			$data = wp_parse_args( $data, $defaults );

			if ( empty( $data['name'] ) || empty( $data['content_url'] ) ) {
				return false;
			}

			$license_id         = self::$id_counter++;
			$data['id']         = $license_id;
			$data['created_at'] = date( 'Y-m-d H:i:s' );
			$data['updated_at'] = date( 'Y-m-d H:i:s' );

			self::$licenses[ $license_id ] = $data;
			return $license_id;
		}

		public function get_license( $id ) {
			$id = intval( $id );
			if ( $id <= 0 || ! isset( self::$licenses[ $id ] ) ) {
				return null;
			}
			return self::$licenses[ $id ];
		}

		public function get_licenses( $args = array() ) {
			$licenses = self::$licenses;

			if ( isset( $args['active'] ) ) {
				$licenses = array_filter(
					$licenses,
					function ( $license ) use ( $args ) {
						return $license['active'] == $args['active'];
					}
				);
			}

			return array_values( $licenses );
		}

		public function update_license( $id, $data ) {
			if ( ! isset( self::$licenses[ $id ] ) ) {
				return false;
			}

			$data                  = $this->sanitize_license_data( $data );
			$data['updated_at']    = date( 'Y-m-d H:i:s' );
			self::$licenses[ $id ] = array_merge( self::$licenses[ $id ], $data );
			return true;
		}

		public function delete_license( $id ) {
			if ( ! isset( self::$licenses[ $id ] ) ) {
				return false;
			}

			unset( self::$licenses[ $id ] );
			return true;
		}

		public function generate_rsl_xml( $license_data ) {
			$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
			$xml .= '<rsl xmlns="https://rslstandard.org/rsl">' . "\n";
			$xml .= '  <content url="' . htmlspecialchars( $license_data['content_url'] ) . '">' . "\n";
			$xml .= '    <license>' . "\n";

			if ( ! empty( $license_data['permits_usage'] ) ) {
				$xml .= '      <permits type="usage">' . htmlspecialchars( $license_data['permits_usage'] ) . '</permits>' . "\n";
			}

			if ( ! empty( $license_data['prohibits_usage'] ) ) {
				$xml .= '      <prohibits type="usage">' . htmlspecialchars( $license_data['prohibits_usage'] ) . '</prohibits>' . "\n";
			}

			$xml .= '      <payment type="' . htmlspecialchars( $license_data['payment_type'] ) . '"';

			$has_amount   = isset( $license_data['amount'] ) && $license_data['amount'] > 0;
			$has_standard = ! empty( $license_data['standard_url'] );

			if ( $has_amount || $has_standard ) {
				$xml .= '>' . "\n";

				if ( $has_amount ) {
					$xml .= '        <amount currency="' . htmlspecialchars( $license_data['currency'] ) . '">';
					$xml .= number_format( $license_data['amount'], 2 );
					$xml .= '</amount>' . "\n";
				}

				if ( $has_standard ) {
					$xml .= '        <standard>' . htmlspecialchars( $license_data['standard_url'] ) . '</standard>' . "\n";
				}

				$xml .= '      </payment>' . "\n";
			} else {
				$xml .= '/>' . "\n";
			}

			if ( ! empty( $license_data['name'] ) ) {
				$xml .= '      <name>' . htmlspecialchars( $license_data['name'] ) . '</name>' . "\n";
			}

			if ( ! empty( $license_data['server_url'] ) ) {
				$xml .= '      <server url="' . htmlspecialchars( $license_data['server_url'] ) . '"/>' . "\n";
			}

			$xml .= '    </license>' . "\n";
			$xml .= '  </content>' . "\n";
			$xml .= '</rsl>';

			return $xml;
		}

		public function validate_license_data( $data ) {
			$valid_payment_types = array( 'free', 'purchase', 'subscription', 'training', 'crawl', 'inference', 'attribution', 'royalty' );
			$valid_currencies    = array( 'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD' );

			if ( ! empty( $data['payment_type'] ) && ! in_array( $data['payment_type'], $valid_payment_types ) ) {
				return new WP_Error( 'invalid_payment_type', 'Invalid payment type' );
			}

			if ( ! empty( $data['currency'] ) && ! in_array( $data['currency'], $valid_currencies ) ) {
				return new WP_Error( 'invalid_currency', 'Invalid currency' );
			}

			return true;
		}

		public function sanitize_license_data( $data ) {
			$sanitized = array();

			// Mirror the real class: only writable license columns survive.
			$columns = array(
				'name',
				'description',
				'content_url',
				'server_url',
				'encrypted',
				'lastmod',
				'permits_usage',
				'permits_user',
				'permits_geo',
				'prohibits_usage',
				'prohibits_user',
				'prohibits_geo',
				'payment_type',
				'standard_url',
				'custom_url',
				'amount',
				'currency',
				'warranty',
				'disclaimer',
				'schema_url',
				'copyright_holder',
				'copyright_type',
				'contact_email',
				'contact_url',
				'terms_url',
				'active',
				'updated_at',
			);
			$data    = array_intersect_key( (array) $data, array_flip( $columns ) );

			foreach ( $data as $key => $value ) {
				switch ( $key ) {
					case 'name':
					case 'description':
						// More aggressive sanitization for XSS prevention
						$clean             = strip_tags( $value );
						$clean             = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $clean );
						$clean             = preg_replace( '/alert\([^)]*\)/', '', $clean );
						$sanitized[ $key ] = $clean;
						break;
					case 'content_url':
						$sanitized[ $key ] = str_replace( '../', '', $value );
						break;
					case 'amount':
						$sanitized[ $key ] = floatval( $value );
						break;
					case 'server_url':
						if ( strpos( $value, 'javascript:' ) === 0 ) {
							$sanitized[ $key ] = 'http://example.org/secure-url';
						} else {
							$sanitized[ $key ] = $value;
						}
						break;
					default:
						$sanitized[ $key ] = is_string( $value ) ? strip_tags( $value ) : $value;
				}
			}

			return $sanitized;
		}

		public function get_license_by_url( $url ) {
			foreach ( self::$licenses as $license ) {
				if ( $this->url_matches_pattern( $url, $license['content_url'] ) ) {
					return $license;
				}
			}
			return null;
		}

		public function get_license_stats() {
			$total   = count( self::$licenses );
			$active  = count(
				array_filter(
					self::$licenses,
					function ( $l ) {
						return $l['active'];
					}
				)
			);
			$by_type = array();

			foreach ( self::$licenses as $license ) {
				$type             = $license['payment_type'];
				$by_type[ $type ] = ( $by_type[ $type ] ?? 0 ) + 1;
			}

			return array(
				'total'    => $total,
				'active'   => $active,
				'inactive' => $total - $active,
				'by_type'  => $by_type,
			);
		}

		public function export_license( $id ) {
			$license = $this->get_license( $id );
			if ( ! $license ) {
				return null;
			}

			return array_merge(
				$license,
				array(
					'rsl_xml' => $this->generate_rsl_xml( $license ),
				)
			);
		}

		public function import_license( $data ) {
			return $this->create_license( $data );
		}

		private function url_matches_pattern( $url, $pattern ) {
			if ( strlen( $pattern ) > 0 && $pattern[0] === '/' ) {
				$u        = parse_url( $url );
				$path     = isset( $u['path'] ) ? $u['path'] : '/';
				$query    = isset( $u['query'] ) ? '?' . $u['query'] : '';
				$haystack = $path . $query;
			} else {
				$haystack = $url;
			}

			$quoted = preg_quote( $pattern, '#' );
			$quoted = str_replace( '\*', '.*', $quoted );
			$quoted = str_replace( '\$', '$', $quoted );
			$regex  = '#^' . $quoted . '#';

			return (bool) preg_match( $regex, $haystack );
		}

		// Reset method for testing
		public static function reset() {
			self::$licenses   = array();
			self::$id_counter = 1;
		}
	}
}
