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

		// Process based on file type
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
		}
	}

	private function embed_rsl_in_image( $attachment_id, $file_path ) {
		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return;
		}

		// Generate XMP metadata with RSL information
		$xmp_data = $this->generate_xmp_rsl( $license_data, $attachment_id );

		// For JPEG images, we can embed XMP data
		if ( function_exists( 'iptcembed' ) ) {
			$this->embed_xmp_in_jpeg( $file_path, $xmp_data );
		}

		// Store RSL metadata in WordPress
		update_post_meta( $attachment_id, '_rsl_embedded', 1 );
		update_post_meta( $attachment_id, '_rsl_xmp_data', $xmp_data );
	}

	private function embed_rsl_in_pdf( $attachment_id, $file_path ) {
		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return;
		}

		// For PDFs, we store the RSL metadata and can generate a companion RSL file
		$rsl_xml = $this->license_handler->generate_rsl_xml( $license_data );

		// Store metadata
		update_post_meta( $attachment_id, '_rsl_embedded', 1 );
		update_post_meta( $attachment_id, '_rsl_xml_data', $rsl_xml );

		// Create companion RSL file with security validation
		$rsl_file_path = str_replace( '.pdf', '.rsl.xml', $file_path );
		$this->safe_write_rsl_file( $rsl_file_path, $rsl_xml );
	}

	private function embed_rsl_in_epub( $attachment_id, $file_path ) {
		$license_data = $this->get_media_license( $attachment_id );

		if ( ! $license_data ) {
			return;
		}

		// For EPUB files, we would need to modify the OPF manifest
		// This is more complex and would require ZIP manipulation

		// For now, store metadata and create companion RSL file
		$rsl_xml = $this->license_handler->generate_rsl_xml( $license_data );

		update_post_meta( $attachment_id, '_rsl_embedded', 1 );
		update_post_meta( $attachment_id, '_rsl_xml_data', $rsl_xml );

		$rsl_file_path = str_replace( '.epub', '.rsl.xml', $file_path );
		$this->safe_write_rsl_file( $rsl_file_path, $rsl_xml );
	}

	private function generate_xmp_rsl( $license_data, $attachment_id ) {
		$file_url = wp_get_attachment_url( $attachment_id );

		// Override content URL for this specific file
		$license_data['content_url'] = $file_url;

		$xmp  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xmp .= '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="RSL/1.0">' . "\n";
		$xmp .= '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n";
		$xmp .= '           xmlns:rsl="https://rslstandard.org/rsl">' . "\n";
		$xmp .= '    <rdf:Description rdf:about="">' . "\n";

		// Generate RSL XML without standalone declaration
		$rsl_xml = $this->license_handler->generate_rsl_xml(
			$license_data,
			array(
				'standalone' => false,
			)
		);

		// Indent RSL XML for XMP embedding
		$rsl_lines = explode( "\n", $rsl_xml );
		foreach ( $rsl_lines as $line ) {
			if ( trim( $line ) ) {
				$xmp .= '      ' . $line . "\n";
			}
		}

		$xmp .= '    </rdf:Description>' . "\n";
		$xmp .= '  </rdf:RDF>' . "\n";
		$xmp .= '</x:xmpmeta>' . "\n";

		return $xmp;
	}

	private function embed_xmp_in_jpeg( $file_path, $xmp_data ) {
		// This is a simplified example - real XMP embedding would require
		// more sophisticated JPEG manipulation

		if ( ! function_exists( 'exif_read_data' ) ) {
			return false;
		}

		// Read current image data with error handling
		$image_data = @file_get_contents( $file_path );
		if ( $image_data === false ) {
			// error_log('RSL: Failed to read image file: ' . $file_path);
			return false;
		}

		// For demonstration, we'll create a sidecar XMP file
		// In a production environment, you'd use a proper XMP library
		$xmp_file = str_replace( '.jpg', '.xmp', $file_path );
		$xmp_file = str_replace( '.jpeg', '.xmp', $xmp_file );

		$this->safe_write_rsl_file( $xmp_file, $xmp_data );

		return true;
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

		// Show current RSL status
		$is_embedded = get_post_meta( $post->ID, '_rsl_embedded', true );
		if ( $is_embedded ) {
			$form_fields['rsl_status'] = array(
				'label' => __( 'RSL Status', 'rsl-wp' ),
				'input' => 'html',
				'html'  => '<span style="color: green;">' . __( 'RSL metadata embedded', 'rsl-wp' ) . '</span>',
				'helps' => __( 'This file contains embedded RSL licensing information.', 'rsl-wp' ),
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

			if ( $license_id ) {
				$license_data = $this->license_handler->get_license( $license_id );
				if ( $license_data ) {
					echo '<strong>' . esc_html( $license_data['name'] ) . '</strong>';
					if ( $is_embedded ) {
						echo '<br><span style="color: green; font-size: 11px;">✓ ' . esc_html__( 'Embedded', 'rsl-wp' ) . '</span>';
					}
					return;
				}
			}

			// Check for global license
			$global_license_id = get_option( 'rsl_global_license_id', 0 );
			if ( $global_license_id > 0 ) {
				$license_data = $this->license_handler->get_license( $global_license_id );
				if ( $license_data ) {
					echo '<em>' . esc_html( $license_data['name'] ) . ' (Global)</em>';
					if ( $is_embedded ) {
						echo '<br><span style="color: green; font-size: 11px;">✓ ' . esc_html__( 'Embedded', 'rsl-wp' ) . '</span>';
					}
					return;
				}
			}

			echo '<span style="color: #666;">' . esc_html__( 'No license', 'rsl-wp' ) . '</span>';
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
			'xmp_data'     => null,
			'xml_data'     => null,
		);

		$license_data = $this->get_media_license( $attachment_id );
		if ( $license_data ) {
			$info['has_license']  = true;
			$info['license_data'] = $license_data;
		}

		$info['is_embedded'] = (bool) get_post_meta( $attachment_id, '_rsl_embedded', true );
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
		// Validate file path to prevent directory traversal
		$file_path = realpath( dirname( $file_path ) ) . '/' . basename( $file_path );

		// Ensure we're writing within WordPress upload directory
		$upload_dir  = wp_upload_dir();
		$upload_path = realpath( $upload_dir['path'] );
		$target_dir  = realpath( dirname( $file_path ) );

		if ( ! $upload_path || ! $target_dir || strpos( $target_dir, $upload_path ) !== 0 ) {
			// error_log('RSL: Attempted to write file outside upload directory: ' . $file_path);
			return false;
		}

		// Check if directory is writable using WP_Filesystem API
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$dir_path = dirname( $file_path );
		if ( ! $wp_filesystem->is_writable( $dir_path ) ) {
			// error_log('RSL: Cannot write to directory: ' . $dir_path);
			return false;
		}

		// Validate file extension
		$allowed_extensions = array( '.xml', '.xmp', '.rsl.xml' );
		$file_extension     = '';
		foreach ( $allowed_extensions as $ext ) {
			if ( substr( $file_path, -strlen( $ext ) ) === $ext ) {
				$file_extension = $ext;
				break;
			}
		}

		if ( empty( $file_extension ) ) {
			// error_log('RSL: Invalid file extension for RSL file: ' . $file_path);
			return false;
		}

		// Attempt to write file
		$bytes_written = @file_put_contents( $file_path, $content );

		if ( $bytes_written === false ) {
			// error_log('RSL: Failed to write RSL file: ' . $file_path);
			return false;
		}

		return true;
	}
}
