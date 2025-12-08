<?php
/**
 * Plugin Name: AI Focused Schema
 * Description: Upload JSON-LD schema, edit fields in admin, and output via shortcode [ai_schema] for use in your Divi footer.
 * Version: 2.0
 * Author: Impact Websites
 * License: GPLv2+
 * GitHub Plugin URI: https://github.com/impact2021/ai-focused-schema
 * Primary Branch: main
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
 * Admin menu: top-level menu item.
 */
add_action( 'admin_menu', function() {
add_menu_page(
'AI Focused Schema',
'AI Schema',
'manage_options',
'ai-focused-schema',
'aifs_admin_page',
'dashicons-code-standards',
30
);
} );

/**
 * Enqueue admin styles for the settings page.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
if ( 'toplevel_page_ai-focused-schema' !== $hook ) {
return;
}
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

update_option( AIFS_OPTION, $schema );
add_settings_error( 'aifs_messages', 'aifs_review_success', 'Review added successfully!', 'success' );
} else {
add_settings_error( 'aifs_messages', 'aifs_review_error', 'Please provide author name and a valid rating (1-5).', 'error' );
}
}
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
$lines = array_filter( array_map( 'trim', preg_split( '/
||
/', $hours ) ) );
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
$lines = array_filter( array_map( 'trim', preg_split( '/
||
/', $same_as ) ) );
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
<textarea id="aifs_additional" name="aifs[additional_json]" rows="6" placeholder='{"menu": "https://example.com/menu.pdf"}'></textarea>
<p class="description">Add any additional schema fields as JSON. These will be merged into the schema. <strong>Note:</strong> Use the Reviews section below to manage reviews and ratings.</p>
</td>
</tr>
</table>

<!-- JSON Preview -->
<h2>Schema Preview</h2>
<p>This is the JSON-LD that will be output by the <code>[ai_schema]</code> shortcode:</p>
<div class="aifs-preview"><?php echo esc_html( $json_preview ); ?></div>

<?php submit_button( 'Save Changes', 'primary', 'aifs_save_fields' ); ?>
</form>

<!-- Reviews Management Section -->
<h2>Customer Reviews</h2>
<p>Add customer reviews below. The aggregate rating and rating count will be automatically calculated based on the reviews you add.</p>

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

if ( ! empty( $ratings ) ) {
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
