<?php
/**
 * Plugin Name: AI Focused Schema
 * Description: Upload JSON-LD schema, edit fields in admin, and output via shortcode [ai_schema] for use in your Divi footer.
 * Version: 2.0
 * Author: Copilot (adapted for impact2021)
 * License: GPLv2+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIFS_OPTION', 'aifs_schema_data' );

/**
 * Activation: set up default option.
 */
register_activation_hook( __FILE__, function() {
	if ( ! get_option( AIFS_OPTION ) ) {
		add_option( AIFS_OPTION, array() );
	}
} );

/**
 * Admin menu: single page under Settings.
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'AI Focused Schema',
		'AI Focused Schema',
		'manage_options',
		'ai-focused-schema',
		'aifs_admin_page'
	);
} );

/**
 * Enqueue admin styles for the settings page.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'settings_page_ai-focused-schema' !== $hook ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', '
		.aifs-form-table th { width: 200px; }
		.aifs-form-table td input[type="text"],
		.aifs-form-table td input[type="url"],
		.aifs-form-table td input[type="email"],
		.aifs-form-table td input[type="tel"] { width: 100%; max-width: 400px; }
		.aifs-form-table td textarea { width: 100%; max-width: 600px; }
		.aifs-json-upload { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
		.aifs-preview { background: #f5f5f5; padding: 15px; border: 1px solid #ccc; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow: auto; }
		.aifs-shortcode-info { background: #e7f3ff; padding: 10px 15px; border-left: 4px solid #0073aa; margin: 15px 0; }
	' );
} );

/**
 * Handle form submissions (JSON upload and field edits).
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle JSON upload/paste.
	if ( isset( $_POST['aifs_upload_json'] ) ) {
		$nonce = isset( $_POST['aifs_upload_nonce'] ) ? wp_unslash( $_POST['aifs_upload_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $nonce, 'aifs_upload_action' ) ) {
			add_settings_error( 'aifs_messages', 'aifs_nonce_error', 'Security check failed.', 'error' );
			return;
		}

		$json_input = isset( $_POST['aifs_json_input'] ) ? wp_unslash( $_POST['aifs_json_input'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parsed = json_decode( $json_input, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error( 'aifs_messages', 'aifs_json_error', 'Invalid JSON: ' . json_last_error_msg(), 'error' );
			return;
		}

		// Sanitize the parsed JSON recursively.
		$sanitized = aifs_sanitize_schema_data( $parsed );
		update_option( AIFS_OPTION, $sanitized );
		add_settings_error( 'aifs_messages', 'aifs_json_success', 'Schema JSON uploaded successfully!', 'success' );
		return;
	}

	// Handle field edits.
	if ( isset( $_POST['aifs_save_fields'] ) ) {
		$nonce = isset( $_POST['aifs_fields_nonce'] ) ? wp_unslash( $_POST['aifs_fields_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $nonce, 'aifs_fields_action' ) ) {
			add_settings_error( 'aifs_messages', 'aifs_nonce_error', 'Security check failed.', 'error' );
			return;
		}

		$schema = get_option( AIFS_OPTION, array() );

		// Update basic fields.
		if ( isset( $_POST['aifs'] ) && is_array( $_POST['aifs'] ) ) {
			$input = wp_unslash( $_POST['aifs'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// @context and @type.
			if ( isset( $input['context'] ) ) {
				$schema['@context'] = sanitize_text_field( $input['context'] );
			}
			if ( isset( $input['type'] ) ) {
				$schema['@type'] = sanitize_text_field( $input['type'] );
			}

			// Basic string fields.
			$string_fields = array( 'name', 'url', 'telephone', 'email', 'description', 'image', 'logo', 'priceRange' );
			foreach ( $string_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$value = sanitize_text_field( $input[ $field ] );
					if ( ! empty( $value ) ) {
						$schema[ $field ] = $value;
					} elseif ( isset( $schema[ $field ] ) ) {
						unset( $schema[ $field ] );
					}
				}
			}

			// Address fields.
			if ( isset( $input['address'] ) && is_array( $input['address'] ) ) {
				$addr = $input['address'];
				$address = array( '@type' => 'PostalAddress' );
				$addr_fields = array(
					'streetAddress'   => 'streetAddress',
					'addressLocality' => 'addressLocality',
					'addressRegion'   => 'addressRegion',
					'postalCode'      => 'postalCode',
					'addressCountry'  => 'addressCountry',
				);
				foreach ( $addr_fields as $key => $schema_key ) {
					if ( ! empty( $addr[ $key ] ) ) {
						$address[ $schema_key ] = sanitize_text_field( $addr[ $key ] );
					}
				}
				if ( count( $address ) > 1 ) {
					$schema['address'] = $address;
				} elseif ( isset( $schema['address'] ) ) {
					unset( $schema['address'] );
				}
			}

			// Geo coordinates.
			if ( isset( $input['geo'] ) && is_array( $input['geo'] ) ) {
				$geo_input = $input['geo'];
				if ( ! empty( $geo_input['latitude'] ) && ! empty( $geo_input['longitude'] ) ) {
					$schema['geo'] = array(
						'@type'     => 'GeoCoordinates',
						'latitude'  => floatval( $geo_input['latitude'] ),
						'longitude' => floatval( $geo_input['longitude'] ),
					);
				} elseif ( isset( $schema['geo'] ) ) {
					unset( $schema['geo'] );
				}
			}

			// Opening hours.
			if ( isset( $input['openingHours'] ) ) {
				$hours = sanitize_textarea_field( $input['openingHours'] );
				if ( ! empty( $hours ) ) {
					$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $hours ) ) );
					if ( ! empty( $lines ) ) {
						$schema['openingHours'] = array_values( $lines );
					}
				} elseif ( isset( $schema['openingHours'] ) ) {
					unset( $schema['openingHours'] );
				}
			}

			// sameAs URLs.
			if ( isset( $input['sameAs'] ) ) {
				$same_as = sanitize_textarea_field( $input['sameAs'] );
				if ( ! empty( $same_as ) ) {
					$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $same_as ) ) );
					if ( ! empty( $lines ) ) {
						$schema['sameAs'] = array_values( $lines );
					}
				} elseif ( isset( $schema['sameAs'] ) ) {
					unset( $schema['sameAs'] );
				}
			}

			// Raw JSON for additional fields.
			if ( isset( $input['additional_json'] ) ) {
				$additional = $input['additional_json'];
				if ( ! empty( trim( $additional ) ) ) {
					$additional_parsed = json_decode( $additional, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $additional_parsed ) ) {
						// Sanitize and merge additional fields into schema.
						$sanitized_additional = aifs_sanitize_schema_data( $additional_parsed );
						foreach ( $sanitized_additional as $key => $value ) {
							// Prevent overwriting critical schema fields.
							if ( ! in_array( $key, array( '@context', '@type' ), true ) ) {
								$schema[ $key ] = $value;
							}
						}
					}
				}
			}
		}

		update_option( AIFS_OPTION, $schema );
		add_settings_error( 'aifs_messages', 'aifs_fields_success', 'Schema fields updated successfully!', 'success' );
	}
} );

/**
 * Admin page HTML.
 */
function aifs_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$schema = get_option( AIFS_OPTION, array() );

	// Extract common fields for display.
	$context         = isset( $schema['@context'] ) ? $schema['@context'] : 'https://schema.org';
	$type            = isset( $schema['@type'] ) ? $schema['@type'] : '';
	$name            = isset( $schema['name'] ) ? $schema['name'] : '';
	$url             = isset( $schema['url'] ) ? $schema['url'] : '';
	$telephone       = isset( $schema['telephone'] ) ? $schema['telephone'] : '';
	$email           = isset( $schema['email'] ) ? $schema['email'] : '';
	$description     = isset( $schema['description'] ) ? $schema['description'] : '';
	$image           = isset( $schema['image'] ) ? $schema['image'] : '';
	$logo            = isset( $schema['logo'] ) ? $schema['logo'] : '';
	$price_range     = isset( $schema['priceRange'] ) ? $schema['priceRange'] : '';

	// Address.
	$street_address   = isset( $schema['address']['streetAddress'] ) ? $schema['address']['streetAddress'] : '';
	$address_locality = isset( $schema['address']['addressLocality'] ) ? $schema['address']['addressLocality'] : '';
	$address_region   = isset( $schema['address']['addressRegion'] ) ? $schema['address']['addressRegion'] : '';
	$postal_code      = isset( $schema['address']['postalCode'] ) ? $schema['address']['postalCode'] : '';
	$address_country  = isset( $schema['address']['addressCountry'] ) ? $schema['address']['addressCountry'] : '';

	// Geo.
	$latitude  = isset( $schema['geo']['latitude'] ) ? $schema['geo']['latitude'] : '';
	$longitude = isset( $schema['geo']['longitude'] ) ? $schema['geo']['longitude'] : '';

	// Opening hours and sameAs.
	$opening_hours = '';
	if ( isset( $schema['openingHours'] ) ) {
		if ( is_array( $schema['openingHours'] ) ) {
			$opening_hours = implode( "\n", $schema['openingHours'] );
		} else {
			$opening_hours = $schema['openingHours'];
		}
	}

	$same_as = '';
	if ( isset( $schema['sameAs'] ) ) {
		if ( is_array( $schema['sameAs'] ) ) {
			$same_as = implode( "\n", $schema['sameAs'] );
		} else {
			$same_as = $schema['sameAs'];
		}
	}

	// Generate JSON preview.
	$json_preview = ! empty( $schema ) ? wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '{}';

	?>
	<div class="wrap">
		<h1>AI Focused Schema</h1>

		<?php settings_errors( 'aifs_messages' ); ?>

		<div class="aifs-shortcode-info">
			<strong>Shortcode:</strong> Use <code>[ai_schema]</code> in your Divi footer (or anywhere else) to output the schema.
		</div>

		<!-- JSON Upload Section -->
		<div class="aifs-json-upload">
			<h2>Upload Schema JSON</h2>
			<p>Paste your JSON-LD schema below to import it. This will replace any existing data.</p>
			<form method="post">
				<?php wp_nonce_field( 'aifs_upload_action', 'aifs_upload_nonce' ); ?>
				<textarea name="aifs_json_input" rows="8" style="width: 100%; max-width: 800px; font-family: monospace;" placeholder='{"@context": "https://schema.org", "@type": "LocalBusiness", "name": "Your Business"}'></textarea>
				<br><br>
				<?php submit_button( 'Upload JSON', 'secondary', 'aifs_upload_json', false ); ?>
			</form>
		</div>

		<!-- Editable Fields Section -->
		<h2>Edit Schema Fields</h2>
		<form method="post">
			<?php wp_nonce_field( 'aifs_fields_action', 'aifs_fields_nonce' ); ?>

			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_context">@context</label></th>
					<td><input type="text" id="aifs_context" name="aifs[context]" value="<?php echo esc_attr( $context ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_type">@type</label></th>
					<td><input type="text" id="aifs_type" name="aifs[type]" value="<?php echo esc_attr( $type ); ?>" placeholder="e.g., LocalBusiness, Organization" /></td>
				</tr>
				<tr>
					<th><label for="aifs_name">Name</label></th>
					<td><input type="text" id="aifs_name" name="aifs[name]" value="<?php echo esc_attr( $name ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_description">Description</label></th>
					<td><textarea id="aifs_description" name="aifs[description]" rows="3"><?php echo esc_textarea( $description ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="aifs_url">URL</label></th>
					<td><input type="url" id="aifs_url" name="aifs[url]" value="<?php echo esc_attr( $url ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_telephone">Telephone</label></th>
					<td><input type="tel" id="aifs_telephone" name="aifs[telephone]" value="<?php echo esc_attr( $telephone ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_email">Email</label></th>
					<td><input type="email" id="aifs_email" name="aifs[email]" value="<?php echo esc_attr( $email ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_image">Image URL</label></th>
					<td><input type="url" id="aifs_image" name="aifs[image]" value="<?php echo esc_attr( $image ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_logo">Logo URL</label></th>
					<td><input type="url" id="aifs_logo" name="aifs[logo]" value="<?php echo esc_attr( $logo ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_priceRange">Price Range</label></th>
					<td><input type="text" id="aifs_priceRange" name="aifs[priceRange]" value="<?php echo esc_attr( $price_range ); ?>" placeholder="e.g., $$" /></td>
				</tr>
			</table>

			<h3>Address</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_street">Street Address</label></th>
					<td><input type="text" id="aifs_street" name="aifs[address][streetAddress]" value="<?php echo esc_attr( $street_address ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_locality">City/Locality</label></th>
					<td><input type="text" id="aifs_locality" name="aifs[address][addressLocality]" value="<?php echo esc_attr( $address_locality ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_region">State/Region</label></th>
					<td><input type="text" id="aifs_region" name="aifs[address][addressRegion]" value="<?php echo esc_attr( $address_region ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_postal">Postal Code</label></th>
					<td><input type="text" id="aifs_postal" name="aifs[address][postalCode]" value="<?php echo esc_attr( $postal_code ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_country">Country</label></th>
					<td><input type="text" id="aifs_country" name="aifs[address][addressCountry]" value="<?php echo esc_attr( $address_country ); ?>" /></td>
				</tr>
			</table>

			<h3>Geo Coordinates</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_lat">Latitude</label></th>
					<td><input type="text" id="aifs_lat" name="aifs[geo][latitude]" value="<?php echo esc_attr( $latitude ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="aifs_lng">Longitude</label></th>
					<td><input type="text" id="aifs_lng" name="aifs[geo][longitude]" value="<?php echo esc_attr( $longitude ); ?>" /></td>
				</tr>
			</table>

			<h3>Opening Hours</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_hours">Opening Hours</label></th>
					<td>
						<textarea id="aifs_hours" name="aifs[openingHours]" rows="4" placeholder="Mo-Fr 09:00-17:00&#10;Sa 10:00-14:00"><?php echo esc_textarea( $opening_hours ); ?></textarea>
						<p class="description">One entry per line. Format: Mo-Fr 09:00-17:00</p>
					</td>
				</tr>
			</table>

			<h3>Social/sameAs Links</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_sameas">sameAs URLs</label></th>
					<td>
						<textarea id="aifs_sameas" name="aifs[sameAs]" rows="4" placeholder="https://facebook.com/yourbusiness&#10;https://twitter.com/yourbusiness"><?php echo esc_textarea( $same_as ); ?></textarea>
						<p class="description">One URL per line (Facebook, Twitter, LinkedIn, etc.)</p>
					</td>
				</tr>
			</table>

			<h3>Additional Fields (Advanced)</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_additional">Additional JSON</label></th>
					<td>
						<textarea id="aifs_additional" name="aifs[additional_json]" rows="6" placeholder='{"aggregateRating": {"@type": "AggregateRating", "ratingValue": "4.5"}}'></textarea>
						<p class="description">Add any additional schema fields as JSON. These will be merged into the schema.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save Changes', 'primary', 'aifs_save_fields' ); ?>
		</form>

		<!-- JSON Preview -->
		<h2>Schema Preview</h2>
		<p>This is the JSON-LD that will be output by the <code>[ai_schema]</code> shortcode:</p>
		<div class="aifs-preview"><?php echo esc_html( $json_preview ); ?></div>

	</div>
	<?php
}

/**
 * Build the JSON-LD script tag.
 *
 * @return string HTML script tag with JSON-LD.
 */
function aifs_build_jsonld() {
	$schema = get_option( AIFS_OPTION, array() );

	if ( empty( $schema ) ) {
		return '';
	}

	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( ! $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Shortcode: [ai_schema]
 * Outputs the JSON-LD schema in a script tag.
 */
add_shortcode( 'ai_schema', function() {
	return aifs_build_jsonld();
} );

/**
 * Backwards-compatible shortcodes.
 */
add_shortcode( 'ai_entity_profile', function() {
	return aifs_build_jsonld();
} );

add_shortcode( 'impact_gbp_schema', function() {
	return aifs_build_jsonld();
} );

/**
 * Recursively sanitize schema data to prevent XSS.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed Sanitized data.
 */
function aifs_sanitize_schema_data( $data ) {
	if ( is_array( $data ) ) {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			// Sanitize keys to prevent injection.
			$clean_key = sanitize_text_field( $key );
			$sanitized[ $clean_key ] = aifs_sanitize_schema_data( $value );
		}
		return $sanitized;
	} elseif ( is_string( $data ) ) {
		// Strip potential script tags and HTML, but preserve URLs.
		if ( filter_var( $data, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $data );
		}
		// For other strings, strip tags but allow basic content.
		return wp_strip_all_tags( $data );
	} elseif ( is_numeric( $data ) ) {
		return $data;
	} elseif ( is_bool( $data ) ) {
		return $data;
	} elseif ( is_null( $data ) ) {
		return null;
	}
	return $data;
}
