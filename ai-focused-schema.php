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
define( 'AIFS_SETTINGS_OPTION', 'aifs_plugin_settings' );
define( 'AIFS_PAGE_SCHEMA_META', '_aifs_page_schema' );
define( 'AIFS_PAGE_SCHEMA_ENABLED_META', '_aifs_page_schema_enabled' );

/**
 * Activation: set up default option.
 */
register_activation_hook( __FILE__, function() {
	if ( ! get_option( AIFS_OPTION ) ) {
		add_option( AIFS_OPTION, array() );
	}
	if ( ! get_option( AIFS_SETTINGS_OPTION ) ) {
		add_option( AIFS_SETTINGS_OPTION, array(
			'auto_output' => 'on', // Enable by default for SEOPress compatibility
		) );
	}
} );

/**
 * Admin menu: top-level menu item and submenu pages.
 */
add_action( 'admin_menu', function() {
	add_menu_page(
		'AI Focused Schema',           // Page title
		'AI Schema',                    // Menu title
		'manage_options',               // Capability
		'ai-focused-schema',            // Menu slug
		'aifs_admin_page',              // Callback function
		'dashicons-code-standards',     // Icon
		30                              // Position (after Comments)
	);
	
	// Add Documentation submenu page
	add_submenu_page(
		'ai-focused-schema',            // Parent slug
		'Documentation',                // Page title
		'Documentation',                // Menu title
		'manage_options',               // Capability
		'ai-focused-schema-docs',       // Menu slug
		'aifs_docs_page'                // Callback function
	);
} );

/**
 * Add metabox for page-specific schema on posts and pages.
 */
add_action( 'add_meta_boxes', function() {
	$post_types = array( 'post', 'page' );
	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'aifs_page_schema',
			'AI Focused Schema - Page Specific',
			'aifs_page_schema_metabox',
			$post_type,
			'normal',
			'default'
		);
	}
} );

/**
 * Display admin notice about SEOpress compatibility.
 */
add_action( 'admin_notices', function() {
	$screen = get_current_screen();
	if ( $screen && 'toplevel_page_ai-focused-schema' === $screen->id ) {
		$settings = get_option( AIFS_SETTINGS_OPTION, array() );
		$auto_output = isset( $settings['auto_output'] ) ? $settings['auto_output'] : 'on';
		$schema = get_option( AIFS_OPTION, array() );
		
		if ( $auto_output === 'on' && ! empty( $schema ) ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>Schema Output Active:</strong> Your schema is being automatically outputted to the page &lt;head&gt; and is detectable by Google and search engines. ';
			echo 'Note: SEOpress will only show schemas it manages directly in its dashboard. To verify your schema is live, use ';
			echo '<a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results Test</a>.</p>';
			echo '</div>';
		}
	}
} );

/**
 * Enqueue admin styles for the plugin page and metabox.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	// Styles for main settings page.
	if ( 'toplevel_page_ai-focused-schema' === $hook ) {
		wp_add_inline_style( 'wp-admin', '
			.aifs-form-table th { width: 200px; }
			.aifs-form-table td input[type="text"],
			.aifs-form-table td input[type="url"],
			.aifs-form-table td input[type="email"],
			.aifs-form-table td input[type="tel"],
			.aifs-form-table td input[type="date"] { width: 100%; max-width: 400px; }
			.aifs-form-table td select { width: 100%; max-width: 400px; }
			.aifs-form-table td textarea { width: 100%; max-width: 600px; }
			.aifs-json-upload { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
			.aifs-preview { background: #f5f5f5; padding: 15px; border: 1px solid #ccc; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow: auto; position: relative; }
			.aifs-shortcode-info { background: #e7f3ff; padding: 10px 15px; border-left: 4px solid #0073aa; margin: 15px 0; }
			.aifs-copy-button { position: absolute; top: 10px; right: 10px; padding: 5px 10px; background: #0073aa; color: #fff; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; }
			.aifs-copy-button:hover { background: #005a87; }
			.aifs-copy-button.copied { background: #46b450; }
		' );
	}
	
	// Styles for post/page metabox.
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		wp_add_inline_style( 'wp-admin', '
			.aifs-metabox-info { background: #f0f7ff; padding: 10px; border-left: 4px solid #0073aa; margin-bottom: 15px; }
			.aifs-metabox-textarea { width: 100%; min-height: 200px; font-family: monospace; font-size: 12px; }
		' );
	}
} );

/**
 * Metabox callback for page-specific schema.
 */
function aifs_page_schema_metabox( $post ) {
	wp_nonce_field( 'aifs_page_schema_save', 'aifs_page_schema_nonce' );
	
	$enabled = get_post_meta( $post->ID, AIFS_PAGE_SCHEMA_ENABLED_META, true );
	$page_schema_json = get_post_meta( $post->ID, AIFS_PAGE_SCHEMA_META, true );
	
	?>
	<div class="aifs-metabox-info">
		<strong>Page-Specific Schema:</strong> Add custom schema for this specific page/post. 
		When enabled, <strong>ONLY this page-specific schema</strong> will be output for this page (the global schema will not be included). 
		This prevents duplication and keeps your sub-pages focused with only the relevant schema.
	</div>
	
	<p>
		<label>
			<input type="checkbox" name="aifs_page_schema_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
			Enable page-specific schema for this page/post
		</label>
	</p>
	
	<p>
		<label for="aifs_page_schema_json"><strong>Schema JSON-LD:</strong></label><br>
		<textarea 
			id="aifs_page_schema_json" 
			name="aifs_page_schema_json" 
			class="aifs-metabox-textarea"
			placeholder='{"@context": "https://schema.org", "@type": "Article", "headline": "Your Title"}'
		><?php echo esc_textarea( $page_schema_json ); ?></textarea>
	</p>
	
	<p class="description">
		Enter your JSON-LD schema. You can paste the complete JSON object (without &lt;script&gt; tags). 
		This will be validated when you save.
	</p>
	<?php
}

/**
 * Save page-specific schema metadata.
 */
add_action( 'save_post', function( $post_id ) {
	// Check nonce.
	if ( ! isset( $_POST['aifs_page_schema_nonce'] ) || 
	     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aifs_page_schema_nonce'] ) ), 'aifs_page_schema_save' ) ) {
		return;
	}
	
	// Check autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	// Check permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	
	// Save enabled status.
	$enabled = isset( $_POST['aifs_page_schema_enabled'] ) ? '1' : '0';
	update_post_meta( $post_id, AIFS_PAGE_SCHEMA_ENABLED_META, $enabled );
	
	// Save schema JSON.
	if ( isset( $_POST['aifs_page_schema_json'] ) ) {
		// Don't use sanitize_textarea_field as it will break JSON.
		// Instead, wp_unslash and then parse/sanitize the decoded JSON.
		$json_input = wp_unslash( $_POST['aifs_page_schema_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json_input = trim( $json_input );
		
		// If enabled and JSON provided, validate it.
		if ( '1' === $enabled && ! empty( $json_input ) ) {
			// Strip <script> tags if user pasted complete HTML snippet.
			// Only match JSON-LD script tags for security.
			if ( preg_match( '/^\s*<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*)<\/script>\s*$/is', $json_input, $matches ) ) {
				$json_input = trim( $matches[1] );
			}
			
			$parsed = json_decode( $json_input, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Invalid JSON - save error state.
				update_post_meta( $post_id, AIFS_PAGE_SCHEMA_META, '' );
				// Add admin notice (will be shown on next page load).
				set_transient( 'aifs_page_schema_error_' . $post_id, 'Invalid JSON: ' . json_last_error_msg(), 45 );
			} else {
				// Valid JSON - update aggregate rating if there are reviews, then sanitize and save.
				aifs_update_aggregate_rating( $parsed );
				$sanitized = aifs_sanitize_schema_data( $parsed );
				$sanitized_json = wp_json_encode( $sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				update_post_meta( $post_id, AIFS_PAGE_SCHEMA_META, $sanitized_json );
				delete_transient( 'aifs_page_schema_error_' . $post_id );
			}
		} else {
			// Not enabled or empty - just save as is.
			update_post_meta( $post_id, AIFS_PAGE_SCHEMA_META, $json_input );
			delete_transient( 'aifs_page_schema_error_' . $post_id );
		}
	}
} );

/**
 * Show admin notice for page schema validation errors.
 */
add_action( 'admin_notices', function() {
	global $post;
	if ( ! $post ) {
		return;
	}
	
	$error = get_transient( 'aifs_page_schema_error_' . $post->ID );
	if ( $error ) {
		echo '<div class="notice notice-error is-dismissible">';
		echo '<p><strong>AI Focused Schema Error:</strong> ' . esc_html( $error ) . '</p>';
		echo '</div>';
		delete_transient( 'aifs_page_schema_error_' . $post->ID );
	}
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
		
		// Strip <script> tags if user pasted complete HTML snippet.
		// Use anchored pattern to only match wrapper tags at beginning/end.
		$json_input = trim( $json_input );
		if ( preg_match( '/^\s*<script[^>]*>(.*)<\/script>\s*$/is', $json_input, $matches ) ) {
			$json_input = trim( $matches[1] );
		}
		
		$parsed = json_decode( $json_input, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error( 'aifs_messages', 'aifs_json_error', 'Invalid JSON: ' . json_last_error_msg(), 'error' );
			return;
		}

		// Sanitize the parsed JSON recursively.
		// First update aggregate rating if there are reviews.
		aifs_update_aggregate_rating( $parsed );
		$sanitized = aifs_sanitize_schema_data( $parsed );
		update_option( AIFS_OPTION, $sanitized );
		add_settings_error( 'aifs_messages', 'aifs_json_success', 'Schema JSON uploaded successfully!', 'success' );
		return;
	}

	// Handle review deletion.
	if ( isset( $_POST['aifs_delete_review'] ) ) {
		$nonce = isset( $_POST['aifs_reviews_nonce'] ) ? wp_unslash( $_POST['aifs_reviews_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $nonce, 'aifs_reviews_action' ) ) {
			add_settings_error( 'aifs_messages', 'aifs_nonce_error', 'Security check failed.', 'error' );
			return;
		}

		$schema = get_option( AIFS_OPTION, array() );
		$review_index = isset( $_POST['review_index'] ) ? intval( $_POST['review_index'] ) : -1;

		// Validate index is within bounds.
		if ( $review_index >= 0 && isset( $schema['review'] ) && is_array( $schema['review'] ) && $review_index < count( $schema['review'] ) ) {
			array_splice( $schema['review'], $review_index, 1 );
			if ( empty( $schema['review'] ) ) {
				unset( $schema['review'] );
			}
			// Recalculate aggregate rating.
			aifs_update_aggregate_rating( $schema );
			// Sanitize schema to ensure no reviews have aggregateRating.
			$schema = aifs_sanitize_schema_data( $schema );
			update_option( AIFS_OPTION, $schema );
			add_settings_error( 'aifs_messages', 'aifs_review_deleted', 'Review deleted successfully!', 'success' );
		}
		return;
	}

	// Handle review addition/edit.
	if ( isset( $_POST['aifs_save_review'] ) ) {
		$nonce = isset( $_POST['aifs_reviews_nonce'] ) ? wp_unslash( $_POST['aifs_reviews_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $nonce, 'aifs_reviews_action' ) ) {
			add_settings_error( 'aifs_messages', 'aifs_nonce_error', 'Security check failed.', 'error' );
			return;
		}

		$schema = get_option( AIFS_OPTION, array() );

		if ( isset( $_POST['aifs_review'] ) && is_array( $_POST['aifs_review'] ) ) {
			$review_input = wp_unslash( $_POST['aifs_review'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$author_name = isset( $review_input['author'] ) ? sanitize_text_field( $review_input['author'] ) : '';
			$rating = isset( $review_input['rating'] ) ? intval( $review_input['rating'] ) : 0;
			$review_body = isset( $review_input['body'] ) ? sanitize_textarea_field( $review_input['body'] ) : '';
			$date = isset( $review_input['date'] ) ? sanitize_text_field( $review_input['date'] ) : '';

			// Validate date format if provided.
			if ( ! empty( $date ) ) {
				// Check basic format first.
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					add_settings_error( 'aifs_messages', 'aifs_review_error', 'Invalid date format. Please use YYYY-MM-DD format.', 'error' );
					return;
				}
				// Validate actual date values.
				$date_parts = explode( '-', $date );
				if ( ! checkdate( (int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0] ) ) {
					add_settings_error( 'aifs_messages', 'aifs_review_error', 'Invalid date. Please provide a valid date.', 'error' );
					return;
				}
			}

			if ( ! empty( $author_name ) && $rating >= 1 && $rating <= 5 ) {
				$new_review = array(
					'@type'        => 'Review',
					'author'       => array(
						'@type' => 'Person',
						'name'  => $author_name,
					),
					'reviewRating' => array(
						'@type'       => 'Rating',
						'ratingValue' => $rating,
						'bestRating'  => 5,
						'worstRating' => 1,
					),
				);

				if ( ! empty( $review_body ) ) {
					$new_review['reviewBody'] = $review_body;
				}

				if ( ! empty( $date ) ) {
					$new_review['datePublished'] = $date;
				}

				if ( ! isset( $schema['review'] ) ) {
					$schema['review'] = array();
				}

				$schema['review'][] = $new_review;

				// Update aggregate rating based on all reviews.
				aifs_update_aggregate_rating( $schema );

				// Sanitize schema to ensure no reviews have aggregateRating.
				$schema = aifs_sanitize_schema_data( $schema );
				update_option( AIFS_OPTION, $schema );
				add_settings_error( 'aifs_messages', 'aifs_review_success', 'Review added successfully!', 'success' );
			} else {
				add_settings_error( 'aifs_messages', 'aifs_review_error', 'Please provide author name and a valid rating (1-5).', 'error' );
			}
		}
		return;
	}

	// Handle plugin settings save.
	if ( isset( $_POST['aifs_save_settings'] ) ) {
		$nonce = isset( $_POST['aifs_settings_nonce'] ) ? wp_unslash( $_POST['aifs_settings_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $nonce, 'aifs_settings_action' ) ) {
			add_settings_error( 'aifs_messages', 'aifs_nonce_error', 'Security check failed.', 'error' );
			return;
		}

		$settings = get_option( AIFS_SETTINGS_OPTION, array() );

		// Auto output setting - explicitly handle checkbox state.
		// Unchecked checkboxes don't send POST data, so we check if the key exists.
		if ( isset( $_POST['aifs_settings'] ) && is_array( $_POST['aifs_settings'] ) ) {
			$settings_input = wp_unslash( $_POST['aifs_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$settings['auto_output'] = isset( $settings_input['auto_output'] ) && $settings_input['auto_output'] === 'on' ? 'on' : 'off';
		} else {
			// If aifs_settings is not in POST, checkbox was unchecked.
			$settings['auto_output'] = 'off';
		}

		update_option( AIFS_SETTINGS_OPTION, $settings );
		add_settings_error( 'aifs_messages', 'aifs_settings_success', 'Plugin settings saved successfully!', 'success' );
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
			$string_fields = array( 'name', 'url', 'telephone', 'email', 'description', 'image', 'logo', 'priceRange', 'category' );
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

			// Founder.
			if ( isset( $input['founder'] ) && is_array( $input['founder'] ) ) {
				$founder_input = $input['founder'];
				if ( ! empty( $founder_input['name'] ) ) {
					$schema['founder'] = array(
						'@type' => 'Person',
						'name'  => sanitize_text_field( $founder_input['name'] ),
					);
				} elseif ( isset( $schema['founder'] ) ) {
					unset( $schema['founder'] );
				}
			}

			// ContactPoint.
			if ( isset( $input['contactPoint'] ) && is_array( $input['contactPoint'] ) ) {
				$contact_input = $input['contactPoint'];
				$contact_point = array( '@type' => 'ContactPoint' );

				if ( ! empty( $contact_input['telephone'] ) ) {
					$contact_point['telephone'] = sanitize_text_field( $contact_input['telephone'] );
				}
				if ( ! empty( $contact_input['contactType'] ) ) {
					$contact_point['contactType'] = sanitize_text_field( $contact_input['contactType'] );
				}
				if ( ! empty( $contact_input['areaServed'] ) ) {
					$contact_point['areaServed'] = sanitize_text_field( $contact_input['areaServed'] );
				}

				// Only add contactPoint if there are fields beyond @type.
				if ( count( $contact_point ) > 1 ) {
					$schema['contactPoint'] = $contact_point;
				} elseif ( isset( $schema['contactPoint'] ) ) {
					unset( $schema['contactPoint'] );
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

		// Update aggregate rating if there are reviews.
		aifs_update_aggregate_rating( $schema );
		// Sanitize schema to ensure no reviews have aggregateRating.
		$schema = aifs_sanitize_schema_data( $schema );
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
	$category        = isset( $schema['category'] ) ? $schema['category'] : '';

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

	// Founder.
	$founder_name = isset( $schema['founder']['name'] ) ? $schema['founder']['name'] : '';

	// ContactPoint.
	$contact_telephone   = isset( $schema['contactPoint']['telephone'] ) ? $schema['contactPoint']['telephone'] : '';
	$contact_type        = isset( $schema['contactPoint']['contactType'] ) ? $schema['contactPoint']['contactType'] : '';
	$contact_area_served = isset( $schema['contactPoint']['areaServed'] ) ? $schema['contactPoint']['areaServed'] : '';

	// Generate JSON preview.
	$json_preview = ! empty( $schema ) ? wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '{}';

	?>
	<div class="wrap">
		<h1>AI Focused Schema</h1>

		<?php settings_errors( 'aifs_messages' ); ?>

		<div class="aifs-shortcode-info">
			<strong>Shortcode:</strong> Use <code>[ai_schema]</code> in your Divi footer (or anywhere else) to output the schema.<br>
			<strong>Page-Specific Schema:</strong> You can add or override fields on individual posts/pages using the metabox in the post editor. Page-specific values will be merged with this global schema.
		</div>

		<!-- Plugin Settings Section -->
		<h2>Plugin Settings</h2>
		<?php
		$plugin_settings = get_option( AIFS_SETTINGS_OPTION, array() );
		$auto_output = isset( $plugin_settings['auto_output'] ) ? $plugin_settings['auto_output'] : 'on';
		?>
		<form method="post">
			<?php wp_nonce_field( 'aifs_settings_action', 'aifs_settings_nonce' ); ?>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_auto_output">Automatic Schema Output</label></th>
					<td>
						<label>
							<input type="checkbox" id="aifs_auto_output" name="aifs_settings[auto_output]" value="on" <?php checked( $auto_output, 'on' ); ?> />
							Automatically output schema in the page &lt;head&gt; section
						</label>
						<p class="description">
							<strong>Recommended: Keep this enabled</strong> for SEOPress PRO and other SEO tools to detect your schema.<br>
							When enabled, schema is automatically added to every page via wp_head.<br>
							The shortcode <code>[ai_schema]</code> will still work for manual placement if needed.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings', 'primary', 'aifs_save_settings' ); ?>
		</form>

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
					<th><label for="aifs_category">Category</label></th>
					<td>
						<input type="text" id="aifs_category" name="aifs[category]" value="<?php echo esc_attr( $category ); ?>" placeholder="e.g., Web Design and Digital Services" />
						<p class="description">Helps Google better classify your business.</p>
					</td>
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
					<td>
						<input type="text" id="aifs_priceRange" name="aifs[priceRange]" value="<?php echo esc_attr( $price_range ); ?>" placeholder="e.g., $$" />
						<p class="description">Use $ symbols (e.g., $, $$, $$$, $$$$) to indicate pricing level.</p>
					</td>
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
						<textarea id="aifs_sameas" name="aifs[sameAs]" rows="4" placeholder="https://www.facebook.com/yourbusiness&#10;https://www.linkedin.com/company/yourbusiness&#10;https://www.instagram.com/yourbusiness"><?php echo esc_textarea( $same_as ); ?></textarea>
						<p class="description">One URL per line. Include Facebook, LinkedIn, Instagram, YouTube, or other social profiles to enhance AI understanding.</p>
					</td>
				</tr>
			</table>

			<h3>Founder (Optional)</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_founder_name">Founder Name</label></th>
					<td>
						<input type="text" id="aifs_founder_name" name="aifs[founder][name]" value="<?php echo esc_attr( $founder_name ); ?>" placeholder="e.g., John Smith" />
						<p class="description">Adding a founder can signal authority and credibility.</p>
					</td>
				</tr>
			</table>

			<h3>Contact Point (Optional)</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_contact_telephone">Telephone</label></th>
					<td>
						<input type="tel" id="aifs_contact_telephone" name="aifs[contactPoint][telephone]" value="<?php echo esc_attr( $contact_telephone ); ?>" placeholder="e.g., +64-21-055-9077" />
					</td>
				</tr>
				<tr>
					<th><label for="aifs_contact_type">Contact Type</label></th>
					<td>
						<input type="text" id="aifs_contact_type" name="aifs[contactPoint][contactType]" value="<?php echo esc_attr( $contact_type ); ?>" placeholder="e.g., customer service" />
						<p class="description">Type of contact (e.g., customer service, sales, support).</p>
					</td>
				</tr>
				<tr>
					<th><label for="aifs_contact_area">Area Served</label></th>
					<td>
						<input type="text" id="aifs_contact_area" name="aifs[contactPoint][areaServed]" value="<?php echo esc_attr( $contact_area_served ); ?>" placeholder="e.g., NZ" />
						<p class="description">Geographic area served (e.g., NZ, US, GB).</p>
					</td>
				</tr>
			</table>

			<h3>Additional Fields (Advanced)</h3>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_additional">Additional JSON</label></th>
					<td>
						<textarea id="aifs_additional" name="aifs[additional_json]" rows="6" placeholder='{"menu": "https://example.com/menu.pdf"}'></textarea>
						<p class="description">Add any additional schema fields as JSON. These will be merged into the schema. <strong>Note:</strong> Use the Reviews section below to manage reviews and ratings.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save Changes', 'primary', 'aifs_save_fields' ); ?>
		</form>

		<!-- Reviews Management Section -->
		<h2>Customer Reviews</h2>
		<p>Add customer reviews manually to your schema. When you have <strong>2 or more reviews</strong>, the aggregate rating and rating count will be automatically calculated and added to your schema. Single reviews will be displayed without an aggregate rating, as per Google's guidelines.</p>

		<?php
		$existing_reviews = isset( $schema['review'] ) && is_array( $schema['review'] ) ? $schema['review'] : array();
		if ( ! empty( $existing_reviews ) ) :
		?>
			<h3>Existing Reviews</h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Author</th>
						<th>Rating</th>
						<th>Review</th>
						<th>Date</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $existing_reviews as $index => $review ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $review['author']['name'] ) ? $review['author']['name'] : 'N/A' ); ?></td>
							<td><?php echo esc_html( isset( $review['reviewRating']['ratingValue'] ) ? $review['reviewRating']['ratingValue'] : 'N/A' ); ?> / 5</td>
							<td><?php echo esc_html( isset( $review['reviewBody'] ) ? wp_trim_words( $review['reviewBody'], 10 ) : '(No review text)' ); ?></td>
							<td><?php echo esc_html( isset( $review['datePublished'] ) ? $review['datePublished'] : 'N/A' ); ?></td>
							<td>
								<form method="post" style="display: inline;">
									<?php wp_nonce_field( 'aifs_reviews_action', 'aifs_reviews_nonce' ); ?>
									<input type="hidden" name="review_index" value="<?php echo esc_attr( $index ); ?>" />
									<button type="submit" name="aifs_delete_review" class="button button-small" onclick="return confirm('Are you sure you want to delete this review?');">Delete</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<br>

			<?php
			// Display aggregate rating.
			if ( isset( $schema['aggregateRating'] ) ) :
				$agg_rating = $schema['aggregateRating'];
			?>
				<p><strong>Aggregate Rating:</strong> 
					<?php echo esc_html( $agg_rating['ratingValue'] ); ?> out of 5 
					(<?php echo esc_html( $agg_rating['ratingCount'] ); ?> review<?php echo ( (int) $agg_rating['ratingCount'] ) !== 1 ? 's' : ''; ?>)
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<h3>Add New Review</h3>
		<form method="post">
			<?php wp_nonce_field( 'aifs_reviews_action', 'aifs_reviews_nonce' ); ?>
			<table class="form-table aifs-form-table">
				<tr>
					<th><label for="aifs_review_author">Author Name *</label></th>
					<td><input type="text" id="aifs_review_author" name="aifs_review[author]" required /></td>
				</tr>
				<tr>
					<th><label for="aifs_review_rating">Rating (1-5) *</label></th>
					<td>
						<select id="aifs_review_rating" name="aifs_review[rating]" required>
							<option value="">Select rating...</option>
							<option value="5">5 - Excellent</option>
							<option value="4">4 - Good</option>
							<option value="3">3 - Average</option>
							<option value="2">2 - Below Average</option>
							<option value="1">1 - Poor</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="aifs_review_body">Review Text</label></th>
					<td>
						<textarea id="aifs_review_body" name="aifs_review[body]" rows="4" placeholder="Optional review text..."></textarea>
					</td>
				</tr>
				<tr>
					<th><label for="aifs_review_date">Date Published</label></th>
					<td>
						<input type="date" id="aifs_review_date" name="aifs_review[date]" />
						<p class="description">Optional. Format: YYYY-MM-DD (e.g., <?php echo esc_html( gmdate( 'Y-m-d' ) ); ?>)</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Add Review', 'secondary', 'aifs_save_review' ); ?>
		</form>

		<!-- JSON Preview -->
		<h2>Schema Preview</h2>
		<p>This is the JSON-LD that will be output by the <code>[ai_schema]</code> shortcode:</p>
		<div class="aifs-preview">
			<button class="aifs-copy-button" onclick="aifsCopySchema(this)">Copy Code</button>
			<pre id="aifs-schema-code"><?php echo esc_html( $json_preview ); ?></pre>
		</div>

		<script>
		function aifsCopySchema(button) {
			var schemaCode = document.getElementById('aifs-schema-code').textContent;
			
			// Use the Clipboard API if available
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(schemaCode).then(function() {
					button.textContent = 'Copied!';
					button.classList.add('copied');
					setTimeout(function() {
						button.textContent = 'Copy Code';
						button.classList.remove('copied');
					}, 2000);
				}).catch(function(err) {
					console.error('Failed to copy:', err);
					alert('Failed to copy to clipboard');
				});
			} else {
				// Fallback for older browsers
				var textarea = document.createElement('textarea');
				textarea.value = schemaCode;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild(textarea);
				textarea.select();
				try {
					document.execCommand('copy');
					button.textContent = 'Copied!';
					button.classList.add('copied');
					setTimeout(function() {
						button.textContent = 'Copy Code';
						button.classList.remove('copied');
					}, 2000);
				} catch (err) {
					console.error('Failed to copy:', err);
					alert('Failed to copy to clipboard');
				}
				document.body.removeChild(textarea);
			}
		}
		</script>

	</div>
	<?php
}

/**
 * Documentation page HTML.
 */
function aifs_docs_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Get README content
	$readme_path = plugin_dir_path( __FILE__ ) . 'README.md';
	$readme_content = '';
	
	if ( file_exists( $readme_path ) && filesize( $readme_path ) < 500000 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$readme_content = file_get_contents( $readme_path );
	}
	
	?>
	<div class="wrap">
		<h1>AI Focused Schema - Documentation</h1>
		
		<div style="max-width: 900px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<?php
			// Simple markdown-to-HTML conversion for display
			$html_content = aifs_simple_markdown_to_html( $readme_content );
			// Output is already escaped by the conversion function
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html_content;
			?>
		</div>
	</div>
	<?php
}

/**
 * Simple markdown to HTML converter.
 *
 * @param string $markdown Markdown content.
 * @return string HTML content.
 */
function aifs_simple_markdown_to_html( $markdown ) {
	if ( empty( $markdown ) ) {
		return '<p>Documentation not found.</p>';
	}
	
	$html = esc_html( $markdown );
	
	// Convert headers
	$html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
	$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
	$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
	
	// Convert bold
	$html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	
	// Convert inline code
	$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
	
	// Convert links
	$html = preg_replace( '/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html );
	
	// Convert lists (mark differently first)
	$html = preg_replace( '/^- (.+)$/m', '<!--UL--><li>$1</li>', $html );
	$html = preg_replace( '/^\d+\. (.+)$/m', '<!--OL--><li>$1</li>', $html );
	
	// Wrap consecutive list items
	$html = preg_replace( '/((?:<!--UL--><li>.*<\/li>\n?)+)/s', '<ul>$1</ul>', $html );
	$html = preg_replace( '/((?:<!--OL--><li>.*<\/li>\n?)+)/s', '<ol>$1</ol>', $html );
	
	// Remove markers
	$html = str_replace( '<!--UL-->', '', $html );
	$html = str_replace( '<!--OL-->', '', $html );
	
	// Convert paragraphs (lines separated by blank lines)
	$lines = explode( "\n", $html );
	$result = array();
	$paragraph = '';
	
	foreach ( $lines as $line ) {
		$trimmed = trim( $line );
		
		// Check if line is a heading, list item, or empty
		if ( preg_match( '/^<h[1-6]>/', $trimmed ) || 
		     preg_match( '/^<\/?(ul|ol|li)>/', $trimmed ) ||
		     empty( $trimmed ) ) {
			// End current paragraph if exists
			if ( ! empty( $paragraph ) ) {
				$result[] = '<p>' . trim( $paragraph ) . '</p>';
				$paragraph = '';
			}
			if ( ! empty( $trimmed ) ) {
				$result[] = $trimmed;
			}
		} else {
			// Accumulate paragraph content
			if ( ! empty( $paragraph ) ) {
				$paragraph .= ' ';
			}
			$paragraph .= $trimmed;
		}
	}
	
	// Add final paragraph if exists
	if ( ! empty( $paragraph ) ) {
		$result[] = '<p>' . trim( $paragraph ) . '</p>';
	}
	
	return implode( "\n", $result );
}

/**
 * Build the JSON-LD script tag.
 *
 * @param int|null $post_id Optional post ID to check for page-specific schema.
 * @return string HTML script tag with JSON-LD.
 */
function aifs_build_jsonld( $post_id = null ) {
	// Start with the global schema.
	$schema = get_option( AIFS_OPTION, array() );
	
	// Check for page-specific schema.
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	
	if ( $post_id ) {
		$enabled = get_post_meta( $post_id, AIFS_PAGE_SCHEMA_ENABLED_META, true );
		
		if ( '1' === $enabled ) {
			$page_schema_json = get_post_meta( $post_id, AIFS_PAGE_SCHEMA_META, true );
			
			if ( ! empty( $page_schema_json ) ) {
				// Page-specific schema is enabled and has content.
				// The JSON is already sanitized and encoded.
				$page_schema = json_decode( $page_schema_json, true );
				
				if ( json_last_error() === JSON_ERROR_NONE && ! empty( $page_schema ) ) {
					// On pages with page-specific schema enabled, use ONLY the page schema.
					// This prevents duplication and schema bloat on sub-pages.
					// The global schema (with all its nested items like reviews, offers, etc.)
					// will only appear on pages WITHOUT page-specific schema.
					$schema = $page_schema;
				}
			}
		}
	}
	
	if ( empty( $schema ) ) {
		return '';
	}

	// wp_json_encode handles all escaping and prevents XSS.
	// All schema data is sanitized via aifs_sanitize_schema_data() on input.
	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( ! $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Merge two schema arrays, with page-specific values overriding global values.
 * When schemas have different @type values, they are combined using @graph.
 *
 * @param array $global_schema Global schema data.
 * @param array $page_schema Page-specific schema data.
 * @return array Merged schema.
 */
function aifs_merge_schemas( $global_schema, $page_schema ) {
	if ( empty( $global_schema ) ) {
		return $page_schema;
	}
	
	if ( empty( $page_schema ) ) {
		return $global_schema;
	}
	
	// Check if both schemas have @type and they are different
	$global_type = $global_schema['@type'] ?? '';
	$page_type = $page_schema['@type'] ?? '';
	
	// If both have @type values and they're different, use @graph to combine them
	if ( ! empty( $global_type ) && ! empty( $page_type ) && $global_type !== $page_type ) {
		// Create a graph structure with both schemas
		// Use page context if available, fallback to global, then default
		$context = $page_schema['@context'] ?? $global_schema['@context'] ?? 'https://schema.org';
		
		// Remove @context from individual schemas as it should only be at root level
		$global_copy = $global_schema;
		$page_copy = $page_schema;
		unset( $global_copy['@context'] );
		unset( $page_copy['@context'] );
		
		return array(
			'@context' => $context,
			'@graph'   => array(
				$global_copy,
				$page_copy,
			),
		);
	}
	
	// If @type is the same or one is missing, perform standard merge
	// Start with global schema
	$merged = $global_schema;
	
	// Merge page-specific values
	foreach ( $page_schema as $key => $value ) {
		if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
			// For nested arrays, merge recursively
			// But only if neither is an indexed array (like reviews)
			if ( aifs_is_associative_array( $value ) && aifs_is_associative_array( $merged[ $key ] ) ) {
				$merged[ $key ] = aifs_merge_schemas( $merged[ $key ], $value );
			} else {
				// For indexed arrays or mixed, replace entirely
				$merged[ $key ] = $value;
			}
		} else {
			// Replace or add the value
			$merged[ $key ] = $value;
		}
	}
	
	return $merged;
}

/**
 * Check if an array is associative (vs indexed).
 *
 * @param array $arr Array to check.
 * @return bool True if associative.
 */
function aifs_is_associative_array( $arr ) {
	if ( ! is_array( $arr ) || empty( $arr ) ) {
		return false;
	}
	return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
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
 * Automatically output schema in wp_head if enabled.
 * This ensures SEOPress PRO and other tools can detect the schema.
 */
add_action( 'wp_head', function() {
	$settings = get_option( AIFS_SETTINGS_OPTION, array() );
	$auto_output = isset( $settings['auto_output'] ) ? $settings['auto_output'] : 'on';

	if ( $auto_output === 'on' ) {
		$output = aifs_build_jsonld();
		if ( ! empty( $output ) ) {
			// Output is safe: JSON is encoded by wp_json_encode() which prevents XSS.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n" . $output . "\n";
		}
	}
}, 1 ); // Priority 1 to output early in the head section.

/**
 * Update aggregate rating based on reviews.
 *
 * @param array &$schema Schema data (passed by reference).
 */
function aifs_update_aggregate_rating( &$schema ) {
	if ( ! isset( $schema['review'] ) || ! is_array( $schema['review'] ) || empty( $schema['review'] ) ) {
		// No reviews, remove aggregate rating.
		if ( isset( $schema['aggregateRating'] ) ) {
			unset( $schema['aggregateRating'] );
		}
		return;
	}

	$ratings = array();
	foreach ( $schema['review'] as $review ) {
		if ( isset( $review['reviewRating']['ratingValue'] ) ) {
			$ratings[] = floatval( $review['reviewRating']['ratingValue'] );
		}
	}

	// Only add aggregateRating if we have 2 or more reviews.
	// According to Google's guidelines, a single review should not have an aggregateRating
	// because "aggregate" implies multiple reviews. This prevents the
	// "Review has multiple aggregate ratings" error in Google Rich Results Test.
	if ( count( $ratings ) >= 2 ) {
		$rating_count = count( $ratings );
		$rating_sum = array_sum( $ratings );
		$rating_value = $rating_sum / $rating_count;

		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => round( $rating_value, 1 ),
			'ratingCount' => $rating_count,
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	} elseif ( isset( $schema['aggregateRating'] ) ) {
		// Remove aggregateRating if we have 0 or 1 reviews.
		unset( $schema['aggregateRating'] );
	}
}

/**
 * Recursively sanitize schema data to prevent XSS.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed Sanitized data.
 */
function aifs_sanitize_schema_data( $data ) {
	if ( is_array( $data ) ) {
		$sanitized = array();
		
		// Check if this is a Review object - reviews should not have aggregateRating
		$is_review = isset( $data['@type'] ) && 'Review' === $data['@type'];
		
		foreach ( $data as $key => $value ) {
			// Remove aggregateRating from Review objects BEFORE sanitizing the key
			// aggregateRating should only exist on the parent entity (LocalBusiness, Product, etc.), not on individual reviews
			// This prevents "Review has multiple aggregate ratings" schema validation error
			if ( $is_review && 'aggregateRating' === $key ) {
				continue; // Skip this property for Review objects
			}
			
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
