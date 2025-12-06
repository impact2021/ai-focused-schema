<?php
/**
 * Plugin Name: AI Focused Schema
 * Description: Site-wide AI-ready business JSON-LD with live Google reviews (Places API). Outputs in the footer and provides [ai_entity_profile] shortcode.
 * Version: 1.0
 * Author: Copilot (adapted for impact2021)
 * License: GPLv2+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IAEP_OPTION', 'iaep_settings' );
define( 'IAEP_TRANSIENT_PREFIX', 'iaep_jsonld_' );

/* Activation defaults */
register_activation_hook( __FILE__, function() {
	$defaults = array(
		'place_id'     => '',
		'api_key'      => '',
		'cache_ttl'    => 3600,
		'name'         => get_bloginfo( 'name' ),
		'url'          => get_home_url(),
		'telephone'    => '',
		'email'        => '',
		'street'       => '',
		'locality'     => '',
		'region'       => '',
		'postal'       => '',
		'country'      => '',
		'latitude'     => '',
		'longitude'    => '',
		'sameAs'       => '',
		'offers_detect' => 1,
		'offers_manual' => '[]',
	);
	if ( ! get_option( IAEP_OPTION ) ) {
		add_option( IAEP_OPTION, $defaults );
	}
} );

/* Admin menu */
add_action( 'admin_menu', function() {
	add_options_page( 'AI Focused Schema', 'AI Focused Schema', 'manage_options', 'iaep', 'iaep_settings_page' );
} );

/* Register settings */
add_action( 'admin_init', function() {
	register_setting( 'iaep_group', IAEP_OPTION, 'iaep_sanitize' );
} );

function iaep_sanitize( $in ) {
	$out = get_option( IAEP_OPTION, array() );
	$out['place_id'] = isset( $in['place_id'] ) ? sanitize_text_field( $in['place_id'] ) : $out['place_id'];
	$out['api_key']  = isset( $in['api_key'] ) ? sanitize_text_field( $in['api_key'] ) : $out['api_key'];
	$out['cache_ttl']= isset( $in['cache_ttl'] ) ? intval( $in['cache_ttl'] ) : $out['cache_ttl'];
	$out['name']     = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : $out['name'];
	$out['url']      = isset( $in['url'] ) ? esc_url_raw( $in['url'] ) : $out['url'];
	$out['telephone']= isset( $in['telephone'] ) ? sanitize_text_field( $in['telephone'] ) : $out['telephone'];
	$out['email']    = isset( $in['email'] ) ? sanitize_email( $in['email'] ) : $out['email'];
	$out['street']   = isset( $in['street'] ) ? sanitize_text_field( $in['street'] ) : $out['street'];
	$out['locality'] = isset( $in['locality'] ) ? sanitize_text_field( $in['locality'] ) : $out['locality'];
	$out['region']   = isset( $in['region'] ) ? sanitize_text_field( $in['region'] ) : $out['region'];
	$out['postal']   = isset( $in['postal'] ) ? sanitize_text_field( $in['postal'] ) : $out['postal'];
	$out['country']  = isset( $in['country'] ) ? sanitize_text_field( $in['country'] ) : $out['country'];
	$out['latitude'] = isset( $in['latitude'] ) ? sanitize_text_field( $in['latitude'] ) : $out['latitude'];
	$out['longitude']= isset( $in['longitude'] ) ? sanitize_text_field( $in['longitude'] ) : $out['longitude'];
	$out['sameAs']   = isset( $in['sameAs'] ) ? wp_kses_post( $in['sameAs'] ) : $out['sameAs'];
	$out['offers_detect'] = isset( $in['offers_detect'] ) ? intval( $in['offers_detect'] ) : 0;
	$out['offers_manual'] = isset( $in['offers_manual'] ) ? $in['offers_manual'] : $out['offers_manual'];
	return $out;
}

/* Settings page (minimal) */
function iaep_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$opts = get_option( IAEP_OPTION );
	?>
	<div class="wrap">
	<h1>AI Focused Schema</h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'iaep_group' ); do_settings_sections( 'iaep_group' ); ?>
		<table class="form-table">
			<tr><th>Google Place ID</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[place_id]" value="<?php echo esc_attr( $opts['place_id'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>API Key</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[api_key]" value="<?php echo esc_attr( $opts['api_key'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Cache TTL (sec)</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[cache_ttl]" value="<?php echo esc_attr( $opts['cache_ttl'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Business name</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[name]" value="<?php echo esc_attr( $opts['name'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Website URL</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[url]" value="<?php echo esc_attr( $opts['url'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Telephone</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[telephone]" value="<?php echo esc_attr( $opts['telephone'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Email</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[email]" value="<?php echo esc_attr( $opts['email'] ); ?>" class="regular-text" /></td></tr>
			<tr><th>Address (street, locality, region, postal, country)</th>
				<td>
					<input name="<?php echo esc_attr( IAEP_OPTION ); ?>[street]" value="<?php echo esc_attr( $opts['street'] ); ?>" class="regular-text" /><br/>
					<input name="<?php echo esc_attr( IAEP_OPTION ); ?>[locality]" value="<?php echo esc_attr( $opts['locality'] ); ?>" />
					<input name="<?php echo esc_attr( IAEP_OPTION ); ?>[region]" value="<?php echo esc_attr( $opts['region'] ); ?>" />
					<input name="<?php echo esc_attr( IAEP_OPTION ); ?>[postal]" value="<?php echo esc_attr( $opts['postal'] ); ?>" /><br/>
					<input name="<?php echo esc_attr( IAEP_OPTION ); ?>[country]" value="<?php echo esc_attr( $opts['country'] ); ?>" />
				</td>
			</tr>
			<tr><th>Geo (lat,lng)</th><td><input name="<?php echo esc_attr( IAEP_OPTION ); ?>[latitude]" value="<?php echo esc_attr( $opts['latitude'] ); ?>" /> <input name="<?php echo esc_attr( IAEP_OPTION ); ?>[longitude]" value="<?php echo esc_attr( $opts['longitude'] ); ?>" /></td></tr>
			<tr><th>sameAs (one per line)</th><td><textarea name="<?php echo esc_attr( IAEP_OPTION ); ?>[sameAs]" rows="3" cols="60"><?php echo esc_textarea( $opts['sameAs'] ); ?></textarea></td></tr>
			<tr><th>Auto-detect /services/ offers</th><td><input type="checkbox" name="<?php echo esc_attr( IAEP_OPTION ); ?>[offers_detect]" value="1" <?php checked( 1, $opts['offers_detect'] ); ?> /></td></tr>
			<tr><th>Manual offers (JSON array)</th><td><textarea name="<?php echo esc_attr( IAEP_OPTION ); ?>[offers_manual]" rows="6" cols="60"><?php echo esc_textarea( $opts['offers_manual'] ); ?></textarea></td></tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<form method="post" style="margin-top:1em;">
		<?php wp_nonce_field( 'iaep_refresh_action', 'iaep_refresh_nonce' ); ?>
		<?php submit_button( 'Refresh now (clear cache)', 'secondary', 'iaep_refresh' ); ?>
	</form>

	</div>
	<?php
}

/* Refresh handler */
add_action( 'admin_init', function() {
	if ( isset( $_POST['iaep_refresh'] ) && current_user_can( 'manage_options' ) ) {
		if ( ! isset( $_POST['iaep_refresh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['iaep_refresh_nonce'] ) ), 'iaep_refresh_action' ) ) {
			return;
		}
		$opts = get_option( IAEP_OPTION );
		$key = IAEP_TRANSIENT_PREFIX . ( ! empty( $opts['place_id'] ) ? md5( $opts['place_id'] ) : 'fallback' );
		delete_transient( $key );
		add_settings_error( 'iaep_messages', 'iaep_refreshed', 'Cache cleared. Next page load will fetch fresh data.' );
	}
} );

/* Helper: get offers (auto + manual) */
function iaep_get_offers() {
	$opts = get_option( IAEP_OPTION );
	$offers = array();

	if ( ! empty( $opts['offers_detect'] ) ) {
		$pages = get_pages( array( 'post_status' => 'publish', 'number' => 50 ) );
		foreach ( $pages as $p ) {
			$permalink = get_permalink( $p );
			if ( $permalink && strpos( $permalink, '/services/' ) !== false ) {
				$offers[] = array(
					'@type' => 'Offer',
					'itemOffered' => array(
						'@type' => 'Service',
						'name' => wp_strip_all_tags( $p->post_title ),
						'description' => wp_strip_all_tags( wp_trim_words( $p->post_excerpt ? $p->post_excerpt : $p->post_content, 30 ) ),
						'url' => esc_url_raw( $permalink ),
					),
				);
			}
		}
	}

	if ( ! empty( $opts['offers_manual'] ) ) {
		$manual = json_decode( $opts['offers_manual'], true );
		if ( is_array( $manual ) ) {
			foreach ( $manual as $m ) {
				if ( empty( $m['name'] ) ) continue;
				$offer = array(
					'@type' => 'Offer',
					'itemOffered' => array(
						'@type' => 'Service',
						'name' => wp_strip_all_tags( $m['name'] ),
						'description' => isset( $m['description'] ) ? wp_strip_all_tags( $m['description'] ) : '',
					),
				);
				if ( ! empty( $m['url'] ) ) $offer['itemOffered']['url'] = esc_url_raw( $m['url'] );
				if ( isset( $m['price'] ) && $m['price'] !== '' ) {
					$currency = isset( $m['priceCurrency'] ) ? sanitize_text_field( $m['priceCurrency'] ) : 'NZD';
					$offer['priceSpecification'] = array(
						'@type' => 'PriceSpecification',
						'price' => (string) $m['price'],
						'priceCurrency' => $currency,
					);
				}
				$offers[] = $offer;
			}
		}
	}

	return $offers;
}

/* Build JSON-LD */
function iaep_build_jsonld() {
	$opts = get_option( IAEP_OPTION );
	$place_id = ! empty( $opts['place_id'] ) ? $opts['place_id'] : '';
	$api_key = ! empty( $opts['api_key'] ) ? $opts['api_key'] : '';
	$transient_key = IAEP_TRANSIENT_PREFIX . ( $place_id ? md5( $place_id ) : 'fallback' );
	$cached = get_transient( $transient_key );
	if ( $cached ) return $cached;

	$schema = array(
		'@context' => 'https://schema.org',
		'@type' => 'ProfessionalService',
		'name' => ! empty( $opts['name'] ) ? wp_strip_all_tags( $opts['name'] ) : get_bloginfo( 'name' ),
		'url' => ! empty( $opts['url'] ) ? esc_url_raw( $opts['url'] ) : get_home_url(),
	);

	if ( ! empty( $opts['telephone'] ) ) $schema['telephone'] = sanitize_text_field( $opts['telephone'] );
	if ( ! empty( $opts['email'] ) ) $schema['email'] = sanitize_email( $opts['email'] );

	$address = array( '@type' => 'PostalAddress' );
	if ( ! empty( $opts['street'] ) ) $address['streetAddress'] = wp_strip_all_tags( $opts['street'] );
	if ( ! empty( $opts['locality'] ) ) $address['addressLocality'] = wp_strip_all_tags( $opts['locality'] );
	if ( ! empty( $opts['region'] ) ) $address['addressRegion'] = wp_strip_all_tags( $opts['region'] );
	if ( ! empty( $opts['postal'] ) ) $address['postalCode'] = wp_strip_all_tags( $opts['postal'] );
	if ( ! empty( $opts['country'] ) ) $address['addressCountry'] = wp_strip_all_tags( $opts['country'] );
	if ( count( $address ) > 1 ) $schema['address'] = $address;

	if ( ! empty( $opts['latitude'] ) && ! empty( $opts['longitude'] ) ) {
		$schema['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $opts['latitude'], 'longitude' => (float) $opts['longitude'] );
	}

	if ( ! empty( $opts['sameAs'] ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $opts['sameAs'] ) );
		$lines = array_filter( array_map( 'trim', $lines ) );
		if ( $lines ) $schema['sameAs'] = array_values( $lines );
	}

	$offers = iaep_get_offers();
	if ( $offers ) $schema['makesOffer'] = $offers;

	// If we have Place ID & API key, fetch Place Details
	if ( $place_id && $api_key ) {
		$fields = implode( ',', array( 'name', 'formatted_phone_number', 'formatted_address', 'geometry', 'rating', 'user_ratings_total', 'reviews', 'url' ) );
		$endpoint = add_query_arg( array( 'place_id' => $place_id, 'fields' => $fields, 'key' => $api_key ), 'https://maps.googleapis.com/maps/api/place/details/json' );
		$response = wp_remote_get( $endpoint, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( ! empty( $data['status'] ) && $data['status'] === 'OK' && ! empty( $data['result'] ) ) {
				$r = $data['result'];
				if ( ! empty( $r['formatted_phone_number'] ) ) $schema['telephone'] = preg_replace( '/\s+/', '', $r['formatted_phone_number'] );
				if ( ! empty( $r['url'] ) ) $schema['url'] = esc_url_raw( $r['url'] );
				if ( ! empty( $r['formatted_address'] ) && empty( $schema['address'] ) ) $schema['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => wp_strip_all_tags( $r['formatted_address'] ), 'addressCountry' => '' );
				if ( ! empty( $r['geometry']['location']['lat'] ) && ! empty( $r['geometry']['location']['lng'] ) && empty( $schema['geo'] ) ) {
					$schema['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $r['geometry']['location']['lat'], 'longitude' => (float) $r['geometry']['location']['lng'] );
				}
				if ( isset( $r['rating'] ) || isset( $r['user_ratings_total'] ) ) {
					$schema['aggregateRating'] = array(
						'@type' => 'AggregateRating',
						'ratingValue' => isset( $r['rating'] ) ? (string) $r['rating'] : '',
						'bestRating' => '5',
						'ratingCount' => isset( $r['user_ratings_total'] ) ? (int) $r['user_ratings_total'] : 0,
					);
				}
				if ( ! empty( $r['reviews'] ) && is_array( $r['reviews'] ) ) {
					$reviews = array();
					foreach ( $r['reviews'] as $rev ) {
						$review = array(
							'@type' => 'Review',
							'author' => array( '@type' => 'Person', 'name' => isset( $rev['author_name'] ) ? wp_strip_all_tags( $rev['author_name'] ) : '' ),
							'reviewBody' => isset( $rev['text'] ) ? wp_strip_all_tags( $rev['text'] ) : '',
							'publisher' => array( '@type' => 'Organization', 'name' => 'Google' ),
						);
						if ( isset( $rev['rating'] ) ) $review['reviewRating'] = array( '@type' => 'Rating', 'ratingValue' => (string) $rev['rating'], 'bestRating' => '5' );
						if ( isset( $rev['time'] ) ) $review['datePublished'] = gmdate( 'c', intval( $rev['time'] ) );
						$reviews[] = $review;
					}
					if ( $reviews ) $schema['review'] = $reviews;
				}
			}
		}
	}

	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( $json ) {
		$script = '<script type="application/ld+json">' . $json . '</script>';
		$ttl = ! empty( $opts['cache_ttl'] ) ? intval( $opts['cache_ttl'] ) : 3600;
		set_transient( $transient_key, $script, $ttl );
		return $script;
	}
	return '';
}

/* Output in footer by default */
add_action( 'wp_footer', function() {
	if ( is_admin() ) return;
	echo iaep_build_jsonld(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is pre-escaped JSON-LD.
}, 1 );

/* Shortcode */
add_shortcode( 'ai_entity_profile', function() {
	return iaep_build_jsonld();
} );

/* Backwards-compatible shortcode name */
add_shortcode( 'impact_gbp_schema', function() {
	return iaep_build_jsonld();
} );
