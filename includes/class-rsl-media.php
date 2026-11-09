<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSL_Media {

	private $license_handler;

	public function __construct() {
		$this->license_handler = new RSL_License();

		add_filter( 'wp_generate_attachment_metadata', array( $this, 'add_rsl_to_media_metadata' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'process_uploaded_media' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_rsl_attachment_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_rsl_attachment_fields' ), 10, 2 );

		// Add media library column
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'show_media_column' ), 10, 2 );
	}

	public function add_rsl_to_media_metadata( $metadata, $attachment_id ) {
		if ( ! get_option( 'rsl_enable_media_metadata', 1 ) ) {
			return $metadata;
		}

		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return $metadata;
		}

		// Add RSL information to metadata
		$metadata['rsl_license'] = array(
			'license_id'   => $license_data['id'],
			'license_name' => $license_data['name'],
			'payment_type' => $license_data['payment_type'],
			'xml_url'      => $this->get_license_xml_url( $license_data['id'] ),
		);

		return $metadata;
	}

	public function process_uploaded_media( $attachment_id ) {
		if ( ! get_option( 'rsl_enable_media_metadata', 1 ) ) {
			return;
		}

		$file_path = get_attached_file( $attachment_id );
		$file_type = wp_check_filetype( $file_path );

		// Process based on file type. Only JPEG supports direct XMP embedding
		// here; other formats get a sidecar file and are labelled accordingly.
		switch ( $file_type['type'] ) {
			case 'image/jpeg':
			case 'image/tiff':
			case 'image/png':
			case 'image/webp':
				$this->embed_rsl_in_image( $attachment_id, $file_path );
				break;

			case 'application/pdf':
				$this->embed_rsl_in_pdf( $attachment_id, $file_path );
				break;

			case 'application/epub+zip':
				$this->embed_rsl_in_epub( $attachment_id, $file_path );
				break;

			case 'audio/mpeg':
			case 'audio/mp3':
			case 'audio/ogg':
			case 'audio/wav':
			case 'video/mp4':
			case 'video/webm':
			case 'video/quicktime':
				$this->write_media_sidecar( $attachment_id, $file_path );
				break;
		}
	}

	private function embed_rsl_in_image( $attachment_id, $file_path ) {
		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return;
		}

		$xmp_data = $this->generate_xmp_rsl( $license_data, $attachment_id );
		$embedded = false;
		$sidecar  = '';

		if ( 'image/jpeg' === wp_check_filetype( $file_path )['type'] && $this->embed_xmp_in_jpeg( $file_path, $xmp_data ) ) {
			$embedded = true;
		} else {
			$sidecar = $this->write_sidecar_file( $file_path, $xmp_data, '.xmp' );
		}

		update_post_meta( $attachment_id, '_rsl_embedded', $embedded ? 1 : 0 );
		update_post_meta( $attachment_id, '_rsl_sidecar', $sidecar );
		update_post_meta( $attachment_id, '_rsl_xmp_data', $xmp_data );
	}

	private function embed_rsl_in_pdf( $attachment_id, $file_path ) {
		$rsl_xml = $this->generate_media_rsl_xml( $attachment_id );

		if ( ! $rsl_xml ) {
			return;
		}

		$sidecar = $this->write_sidecar_file( $file_path, $rsl_xml, '.rsl.xml' );

		update_post_meta( $attachment_id, '_rsl_embedded', 0 );
		update_post_meta( $attachment_id, '_rsl_sidecar', $sidecar );
		update_post_meta( $attachment_id, '_rsl_xml_data', $rsl_xml );
	}

	private function embed_rsl_in_epub( $attachment_id, $file_path ) {
		$rsl_xml = $this->generate_media_rsl_xml( $attachment_id );

		if ( ! $rsl_xml ) {
			return;
		}

		$sidecar = $this->write_sidecar_file( $file_path, $rsl_xml, '.rsl.xml' );

		update_post_meta( $attachment_id, '_rsl_embedded', 0 );
		update_post_meta( $attachment_id, '_rsl_sidecar', $sidecar );
		update_post_meta( $attachment_id, '_rsl_xml_data', $rsl_xml );
	}

	private function write_media_sidecar( $attachment_id, $file_path ) {
		$rsl_xml = $this->generate_media_rsl_xml( $attachment_id );

		if ( ! $rsl_xml ) {
			return;
		}

		$sidecar = $this->write_sidecar_file( $file_path, $rsl_xml, '.rsl.xml' );

		update_post_meta( $attachment_id, '_rsl_embedded', 0 );
		update_post_meta( $attachment_id, '_rsl_sidecar', $sidecar );
		update_post_meta( $attachment_id, '_rsl_xml_data', $rsl_xml );
	}

	private function write_sidecar_file( $file_path, $content, $suffix ) {
		$sidecar = $file_path . $suffix;
		return $this->safe_write_rsl_file( $sidecar, $content ) ? $sidecar : '';
	}

	private function generate_xmp_rsl( $license_data, $attachment_id ) {
		$file_url = wp_get_attachment_url( $attachment_id );

		// A media fragment describes this exact file, not the post or site.
		$license_data['content_url'] = $file_url;

		$rsl_xml = $this->license_handler->generate_rsl_xml(
			$license_data,
			array(
				'standalone' => false,
				'root'       => false,
				'prefix'     => 'rsl:',
			)
		);

		$xmp  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xmp .= '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="RSL/1.0">' . "\n";
		$xmp .= '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n";
		$xmp .= '    <rdf:Description rdf:about="" xmlns:rsl="' . esc_attr( RSL_NAMESPACE ) . '">' . "\n";
		$xmp .= '      <rsl:rsl rdf:parseType="Literal">' . "\n";
		$xmp .= $this->indent_xml( $rsl_xml, 8 );
		$xmp .= '      </rsl:rsl>' . "\n";
		$xmp .= '    </rdf:Description>' . "\n";
		$xmp .= '  </rdf:RDF>' . "\n";
		$xmp .= '</x:xmpmeta>' . "\n";

		return $xmp;
	}

	private function indent_xml( $xml, $spaces ) {
		$indent = str_repeat( ' ', $spaces );
		$lines  = array();

		foreach ( explode( "\n", $xml ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$lines[] = $indent . $line;
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	private function embed_xmp_in_jpeg( $file_path, $xmp_data ) {
		$image_data = @file_get_contents( $file_path );
		if ( false === $image_data || "\xFF\xD8" !== substr( $image_data, 0, 2 ) ) {
			return false;
		}

		$header        = "http://ns.adobe.com/xap/1.0/\0" . $xmp_data;
		$segment_length = strlen( $header ) + 2;

		if ( $segment_length > 65535 ) {
			return false;
		}

		$segment = "\xFF\xE1" . pack( 'n', $segment_length ) . $header;
		$stripped = $this->strip_jpeg_xmp( $image_data );
		$updated  = "\xFF\xD8" . $segment . substr( $stripped, 2 );

		return $this->safe_write_attachment_file( $file_path, $updated );
	}

	private function strip_jpeg_xmp( $image_data ) {
		$length = strlen( $image_data );
		$offset = 2;
		$output = substr( $image_data, 0, 2 );

		while ( $offset + 4 <= $length ) {
			if ( "\xFF" !== $image_data[ $offset ] ) {
				break;
			}

			$marker = ord( $image_data[ $offset + 1 ] );

			// Start of scan or end of image: no more metadata segments.
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				break;
			}

			$segment_length = unpack( 'n', substr( $image_data, $offset + 2, 2 ) )[1];
			if ( $segment_length < 2 ) {
				break;
			}

			$segment = substr( $image_data, $offset, 2 + $segment_length );

			if ( 0xE1 === $marker && false !== strpos( $segment, "http://ns.adobe.com/xap/1.0/\0" ) ) {
				$offset += 2 + $segment_length;
				continue;
			}

			$output .= $segment;
			$offset += 2 + $segment_length;
		}

		return $output . substr( $image_data, $offset );
	}

	private function get_media_license( $attachment_id ) {
		// Check for attachment-specific license
		$attachment_license_id = get_post_meta( $attachment_id, '_rsl_license_id', true );
		if ( $attachment_license_id ) {
			$license_data = $this->license_handler->get_license( $attachment_license_id );
			if ( $license_data && $license_data['active'] ) {
				return $license_data;
			}
		}

		// Fall back to global license
		$global_license_id = get_option( 'rsl_global_license_id', 0 );
		if ( $global_license_id > 0 ) {
			$license_data = $this->license_handler->get_license( $global_license_id );
			if ( $license_data && $license_data['active'] ) {
				return $license_data;
			}
		}

		return null;
	}

	public function add_rsl_attachment_fields( $form_fields, $post ) {
		$licenses         = $this->license_handler->get_licenses();
		$selected_license = get_post_meta( $post->ID, '_rsl_license_id', true );

		$options = '<option value="">' . __( 'Use Global License', 'rsl-wp' ) . '</option>';
		foreach ( $licenses as $license ) {
			$selected = selected( $selected_license, $license['id'], false );
			$options .= '<option value="' . esc_attr( $license['id'] ) . '" ' . $selected . '>';
			$options .= esc_html( $license['name'] );
			$options .= '</option>';
		}

		$form_fields['rsl_license'] = array(
			'label' => __( 'RSL License', 'rsl-wp' ),
			'input' => 'html',
			'html'  => '<select name="attachments[' . $post->ID . '][rsl_license_id]">' . $options . '</select>',
			'helps' => __( 'Select RSL license for this media file.', 'rsl-wp' ),
		);

		$is_embedded = get_post_meta( $post->ID, '_rsl_embedded', true );
		$sidecar     = get_post_meta( $post->ID, '_rsl_sidecar', true );

		if ( $is_embedded ) {
			$form_fields['rsl_status'] = array(
				'label' => __( 'RSL Status', 'rsl-wp' ),
				'input' => 'html',
				'html'  => '<span style="color: green;">' . __( 'RSL metadata embedded', 'rsl-wp' ) . '</span>',
				'helps' => __( 'This file contains embedded RSL licensing information.', 'rsl-wp' ),
			);
		} elseif ( $sidecar ) {
			$form_fields['rsl_status'] = array(
				'label' => __( 'RSL Status', 'rsl-wp' ),
				'input' => 'html',
				'html'  => '<span style="color: #996800;">' . __( 'RSL sidecar file', 'rsl-wp' ) . '</span>',
				'helps' => sprintf(
					/* translators: %s: sidecar file name. */
					__( 'RSL metadata is stored next to this file as %s.', 'rsl-wp' ),
					esc_html( wp_basename( $sidecar ) )
				),
			);
		}

		return $form_fields;
	}

	public function save_rsl_attachment_fields( $post, $attachment ) {
		if ( isset( $attachment['rsl_license_id'] ) ) {
			$license_id = intval( $attachment['rsl_license_id'] );
			if ( $license_id > 0 ) {
				update_post_meta( $post['ID'], '_rsl_license_id', $license_id );
			} else {
				delete_post_meta( $post['ID'], '_rsl_license_id' );
			}

			// Re-process the file to embed new license
			if ( get_option( 'rsl_enable_media_metadata', 1 ) ) {
				$this->process_uploaded_media( $post['ID'] );
			}
		}

		return $post;
	}

	public function add_media_column( $columns ) {
		$columns['rsl_license'] = __( 'RSL License', 'rsl-wp' );
		return $columns;
	}

	public function show_media_column( $column_name, $post_id ) {
		if ( $column_name === 'rsl_license' ) {
			$license_id  = get_post_meta( $post_id, '_rsl_license_id', true );
			$is_embedded = get_post_meta( $post_id, '_rsl_embedded', true );
			$sidecar     = get_post_meta( $post_id, '_rsl_sidecar', true );

			if ( $license_id ) {
				$license_data = $this->license_handler->get_license( $license_id );
				if ( $license_data ) {
					echo '<strong>' . esc_html( $license_data['name'] ) . '</strong>';
					$this->show_metadata_status( $is_embedded, $sidecar );
					return;
				}
			}

			// Check for global license
			$global_license_id = get_option( 'rsl_global_license_id', 0 );
			if ( $global_license_id > 0 ) {
				$license_data = $this->license_handler->get_license( $global_license_id );
				if ( $license_data ) {
					echo '<em>' . esc_html( $license_data['name'] ) . ' (Global)</em>';
					$this->show_metadata_status( $is_embedded, $sidecar );
					return;
				}
			}

			echo '<span style="color: #666;">' . esc_html__( 'No license', 'rsl-wp' ) . '</span>';
		}
	}

	private function show_metadata_status( $is_embedded, $sidecar ) {
		if ( $is_embedded ) {
			echo '<br><span style="color: green; font-size: 11px;">' . esc_html__( 'Embedded', 'rsl-wp' ) . '</span>';
		} elseif ( $sidecar ) {
			echo '<br><span style="color: #996800; font-size: 11px;">' . esc_html__( 'Sidecar', 'rsl-wp' ) . '</span>';
		}
	}

	private function get_license_xml_url( $license_id ) {
		return RSL_Server::get_license_url( $license_id );
	}

	public function get_media_rsl_info( $attachment_id ) {
		$info = array(
			'has_license'  => false,
			'license_data' => null,
			'is_embedded'  => false,
			'sidecar'      => '',
			'xmp_data'     => null,
			'xml_data'     => null,
		);

		$license_data = $this->get_media_license( $attachment_id );
		if ( $license_data ) {
			$info['has_license']  = true;
			$info['license_data'] = $license_data;
		}

		$info['is_embedded'] = (bool) get_post_meta( $attachment_id, '_rsl_embedded', true );
		$info['sidecar']     = (string) get_post_meta( $attachment_id, '_rsl_sidecar', true );
		$info['xmp_data']    = get_post_meta( $attachment_id, '_rsl_xmp_data', true );
		$info['xml_data']    = get_post_meta( $attachment_id, '_rsl_xml_data', true );

		return $info;
	}

	public function generate_media_rsl_xml( $attachment_id ) {
		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return null;
		}

		// Override content URL to point to the media file
		$license_data['content_url'] = wp_get_attachment_url( $attachment_id );

		return $this->license_handler->generate_rsl_xml( $license_data );
	}

	private function safe_write_rsl_file( $file_path, $content ) {
		if ( ! $this->is_safe_upload_path( $file_path ) ) {
			return false;
		}

		$allowed_extensions = array( '.xml', '.xmp', '.rsl.xml' );
		$allowed            = false;
		foreach ( $allowed_extensions as $ext ) {
			if ( substr( $file_path, -strlen( $ext ) ) === $ext ) {
				$allowed = true;
				break;
			}
		}

		if ( ! $allowed ) {
			return false;
		}

		return false !== @file_put_contents( $file_path, $content );
	}

	private function safe_write_attachment_file( $file_path, $content ) {
		if ( ! preg_match( '/\.jpe?g$/i', $file_path ) || ! $this->is_safe_upload_path( $file_path ) ) {
			return false;
		}

		return false !== @file_put_contents( $file_path, $content );
	}

	private function is_safe_upload_path( $file_path ) {
		$upload_dir  = wp_upload_dir();
		$upload_path = realpath( $upload_dir['basedir'] );
		$target_dir  = realpath( dirname( $file_path ) );

		return $upload_path && $target_dir && 0 === strpos( $target_dir, $upload_path );
	}
}
