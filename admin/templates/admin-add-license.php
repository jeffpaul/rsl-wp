<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_handler = new RSL_License();
$is_edit         = ! empty( $license_data );
$page_title      = $is_edit ? __( 'Edit License', 'rsl-wp' ) : __( 'Add New License', 'rsl-wp' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<img src="<?php echo esc_url( RSL_PLUGIN_URL . 'admin/images/rsl-logo.png' ); ?>" 
			alt="RSL" class="rsl-admin-icon">
		<?php echo esc_html( $page_title ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-licenses' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'View All Licenses', 'rsl-wp' ); ?>
	</a>
	<hr class="wp-header-end">
	
	<?php
	// Display admin notices
	if ( function_exists( 'settings_errors' ) ) {
		settings_errors();
	}
	do_action( 'admin_notices' );
	?>
	
	<div id="rsl-message" class="notice rsl-hidden"></div>
	
	<form id="rsl-license-form" method="post">
		<?php wp_nonce_field( 'rsl_license_form', 'rsl_nonce' ); ?>
		
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="license_id" value="<?php echo esc_attr( $license_id ); ?>">
		<?php endif; ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="name"><?php esc_html_e( 'License Name', 'rsl-wp' ); ?> *</label>
					</th>
					<td>
						<input type="text" id="name" name="name" class="regular-text" required
								value="<?php echo esc_attr( $license_data['name'] ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'A descriptive name for this license configuration.', 'rsl-wp' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="description"><?php esc_html_e( 'Description', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $license_data['description'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional description of this license.', 'rsl-wp' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="content_url"><?php esc_html_e( 'Content URL', 'rsl-wp' ); ?> *</label>
					</th>
					<td>
						<input type="text" id="content_url" name="content_url" class="regular-text" required
								value="<?php echo esc_attr( $license_data['content_url'] ?? '' ); ?>"
								placeholder="https://example.com/content/ or / for site root">
						<p class="description">
							<?php esc_html_e( 'URL pattern for licensed content. Use "/" for entire site, or specific paths like "/images/". Supports wildcards (* and $).', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="server_option"><?php esc_html_e( 'License Server', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'License Server Options', 'rsl-wp' ); ?></legend>
							
							<p>
								<label>
									<input type="radio" name="server_option" value="builtin" 
											<?php checked( empty( $license_data['server_url'] ) || wp_parse_url( $license_data['server_url'] ?? '', PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>>
									<strong><?php esc_html_e( 'Built-in License Server', 'rsl-wp' ); ?></strong> (<?php esc_html_e( 'Recommended', 'rsl-wp' ); ?>)
								</label>
								<br>
								<span class="description" style="margin-left: 25px;">
									<?php esc_html_e( 'Use this WordPress site as the license server. Handles free licenses immediately and integrates with WooCommerce for paid licensing.', 'rsl-wp' ); ?>
								</span>
							</p>
							
							<p>
								<label>
									<input type="radio" name="server_option" value="external" 
											<?php checked( ! empty( $license_data['server_url'] ) && wp_parse_url( $license_data['server_url'] ?? '', PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>>
									<strong><?php esc_html_e( 'External License Server', 'rsl-wp' ); ?></strong>
								</label>
								<br>
								<span class="description" style="margin-left: 25px;">
									<?php esc_html_e( 'Use an external RSL License Server (e.g., RSL Collective) for centralized licensing and payment processing.', 'rsl-wp' ); ?>
								</span>
							</p>
							
							<div id="external_server_url_field" style="margin-top: 15px; padding-left: 25px; display: none;">
								<label for="server_url"><?php esc_html_e( 'External Server URL:', 'rsl-wp' ); ?></label><br>
								<input type="url" id="server_url" name="server_url" class="regular-text"
										value="<?php echo esc_attr( $license_data['server_url'] ?? '' ); ?>"
										placeholder="https://rslcollective.org/api">
								<p class="description">
									<?php esc_html_e( 'Enter the URL of the external RSL License Server API endpoint.', 'rsl-wp' ); ?>
								</p>
							</div>
						</fieldset>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Content Encryption', 'rsl-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="encrypted" value="1" 
									<?php checked( $license_data['encrypted'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Content is encrypted', 'rsl-wp' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Check if the licensed content requires decryption keys from the license server.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2><?php esc_html_e( 'Permissions', 'rsl-wp' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="permits_usage"><?php esc_html_e( 'Permitted Usage', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="permits_usage" name="permits_usage" multiple class="rsl-multiselect">
							<?php
							$selected_usage = explode( ',', $license_data['permits_usage'] ?? '' );
							foreach ( $license_handler->get_usage_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_usage ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select permitted usage types. Leave empty to permit all usage types.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="permits_user"><?php esc_html_e( 'Permitted Users', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="permits_user" name="permits_user" multiple class="rsl-multiselect">
							<?php
							$selected_user = explode( ',', $license_data['permits_user'] ?? '' );
							foreach ( $license_handler->get_user_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_user ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select permitted user types. Leave empty to permit all user types.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="permits_geo"><?php esc_html_e( 'Permitted Geography', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="text" id="permits_geo" name="permits_geo" class="regular-text"
								value="<?php echo esc_attr( $license_data['permits_geo'] ?? '' ); ?>"
								placeholder="US,EU,CA">
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of permitted countries/regions (ISO 3166-1 alpha-2 codes).', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2><?php esc_html_e( 'Restrictions', 'rsl-wp' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="prohibits_usage"><?php esc_html_e( 'Prohibited Usage', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="prohibits_usage" name="prohibits_usage" multiple class="rsl-multiselect">
							<?php
							$selected_prohibited = explode( ',', $license_data['prohibits_usage'] ?? '' );
							foreach ( $license_handler->get_usage_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_prohibited ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select explicitly prohibited usage types.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="prohibits_user"><?php esc_html_e( 'Prohibited Users', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="prohibits_user" name="prohibits_user" multiple class="rsl-multiselect">
							<?php
							$selected_prohibited_user = explode( ',', $license_data['prohibits_user'] ?? '' );
							foreach ( $license_handler->get_user_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_prohibited_user ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select explicitly prohibited user types.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="prohibits_geo"><?php esc_html_e( 'Prohibited Geography', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="text" id="prohibits_geo" name="prohibits_geo" class="regular-text"
								value="<?php echo esc_attr( $license_data['prohibits_geo'] ?? '' ); ?>"
								placeholder="CN,RU">
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of prohibited countries/regions (ISO 3166-1 alpha-2 codes).', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2><?php esc_html_e( 'Payment & Compensation', 'rsl-wp' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="payment_type"><?php esc_html_e( 'Payment Type', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="payment_type" name="payment_type" class="regular-text">
							<?php foreach ( $license_handler->get_payment_options() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( $license_data['payment_type'] ?? 'free', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the payment model for this license. Set amount to 0 for free licenses of any type.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr id="payment_amount_row" style="display: none;">
					<th scope="row">
						<label for="amount"><?php esc_html_e( 'Amount', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="number" id="amount" name="amount" step="0.0001" min="0" class="small-text"
								value="<?php echo esc_attr( $license_data['amount'] ?? '0' ); ?>">
						<select name="currency" class="regular-text">
							<option value="USD" <?php selected( $license_data['currency'] ?? 'USD', 'USD' ); ?>>USD</option>
							<option value="EUR" <?php selected( $license_data['currency'] ?? 'USD', 'EUR' ); ?>>EUR</option>
							<option value="GBP" <?php selected( $license_data['currency'] ?? 'USD', 'GBP' ); ?>>GBP</option>
							<option value="BTC" <?php selected( $license_data['currency'] ?? 'USD', 'BTC' ); ?>>BTC</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Set to 0 for free licenses. Amounts > 0 require WooCommerce for payment processing.', 'rsl-wp' ); ?>
							<?php if ( ! $woocommerce_active ) : ?>
								<br><span style="color: #d63638;">
									<strong><?php esc_html_e( 'WooCommerce not installed:', 'rsl-wp' ); ?></strong>
									<?php esc_html_e( 'Only amount = 0 will work for token generation.', 'rsl-wp' ); ?>
									<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
										<?php esc_html_e( 'Install WooCommerce', 'rsl-wp' ); ?>
									</a>
								</span>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="standard_url"><?php esc_html_e( 'Standard License URL', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="url" id="standard_url" name="standard_url" class="regular-text"
								value="<?php echo esc_attr( $license_data['standard_url'] ?? '' ); ?>"
								placeholder="https://creativecommons.org/licenses/by/4.0/">
						<p class="description">
							<?php esc_html_e( 'URL to standard license terms (e.g., Creative Commons, RSL Collective).', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="custom_url"><?php esc_html_e( 'Custom License URL', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="url" id="custom_url" name="custom_url" class="regular-text"
								value="<?php echo esc_attr( $license_data['custom_url'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'URL to custom licensing terms and contact information.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2><?php esc_html_e( 'Legal Information', 'rsl-wp' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="warranty"><?php esc_html_e( 'Warranties', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="warranty" name="warranty" multiple class="rsl-multiselect">
							<?php
							$selected_warranty = explode( ',', $license_data['warranty'] ?? '' );
							foreach ( $license_handler->get_warranty_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_warranty ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select warranties provided with this license.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="disclaimer"><?php esc_html_e( 'Disclaimers', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<select id="disclaimer" name="disclaimer" multiple class="rsl-multiselect">
							<?php
							$selected_disclaimer = explode( ',', $license_data['disclaimer'] ?? '' );
							foreach ( $license_handler->get_disclaimer_options() as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" 
										<?php selected( in_array( $value, $selected_disclaimer ), true ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select disclaimers for this license.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2><?php esc_html_e( 'Additional Information', 'rsl-wp' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="schema_url"><?php esc_html_e( 'Schema.org URL', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="url" id="schema_url" name="schema_url" class="regular-text"
								value="<?php echo esc_attr( $license_data['schema_url'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'URL to Schema.org CreativeWork metadata for this content.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="copyright_holder"><?php esc_html_e( 'Copyright Holder', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="text" id="copyright_holder" name="copyright_holder" class="regular-text"
								value="<?php echo esc_attr( $license_data['copyright_holder'] ?? '' ); ?>">
						
						<select name="copyright_type" class="regular-text">
							<option value=""><?php esc_html_e( 'Select Type', 'rsl-wp' ); ?></option>
							<option value="person" <?php selected( $license_data['copyright_type'] ?? '', 'person' ); ?>>
								<?php esc_html_e( 'Person', 'rsl-wp' ); ?>
							</option>
							<option value="organization" <?php selected( $license_data['copyright_type'] ?? '', 'organization' ); ?>>
								<?php esc_html_e( 'Organization', 'rsl-wp' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="contact_email"><?php esc_html_e( 'Contact Email', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="email" id="contact_email" name="contact_email" class="regular-text"
								value="<?php echo esc_attr( $license_data['contact_email'] ?? '' ); ?>">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="contact_url"><?php esc_html_e( 'Contact URL', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="url" id="contact_url" name="contact_url" class="regular-text"
								value="<?php echo esc_attr( $license_data['contact_url'] ?? '' ); ?>">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="terms_url"><?php esc_html_e( 'Terms URL', 'rsl-wp' ); ?></label>
					</th>
					<td>
						<input type="url" id="terms_url" name="terms_url" class="regular-text"
								value="<?php echo esc_attr( $license_data['terms_url'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'URL to additional legal information about the license.', 'rsl-wp' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'rsl-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="active" value="1" 
									<?php checked( $license_data['active'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'License is active', 'rsl-wp' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		
		<?php submit_button( $is_edit ? __( 'Update License', 'rsl-wp' ) : __( 'Create License', 'rsl-wp' ) ); ?>
	</form>
</div>

