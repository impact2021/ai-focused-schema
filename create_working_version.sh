#!/bin/bash
# This script creates the simplified "working" version of the plugin

cat > ai-focused-schema-new.php << 'EOF_PART1'
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
EOF_PART1

chmod +x create_working_version.sh
echo "Script created"
