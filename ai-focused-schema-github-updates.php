<?php
/**
 * AI Focused Schema — GitHub Releases update checker
 *
 * Requires: The release on GitHub should include an asset named "ai-focused-schema.zip"
 *            (the code below will fall back to the release zipball if that asset is not found).
 *
 * Install location: plugin root. This code assumes the installed plugin folder is named:
 *   ai-focused-schema
 * and the main plugin file path (inside that folder) is:
 *   ai-focused-schema/ai-focused-schema.php
 *
 * If your installed plugin folder or main file path differs, update $plugin_file below.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'site_transient_update_plugins', 'aifs_github_check_for_plugin_update' );

/**
 * Fetch latest release info from GitHub (cached for 12 hours).
 *
 * @return array|false ['version' => 'x.y.z', 'download_url' => 'https://...'] or false on error
 */
function aifs_github_get_latest_release() {
    $cache_key = 'aifs_github_latest_release';
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $repo = 'impact2021/ai-focused-schema';
    $url  = "https://api.github.com/repos/{$repo}/releases/latest";

    $args = array(
        'headers' => array(
            // GitHub requires a user-agent header
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            'Accept'     => 'application/vnd.github.v3+json',
        ),
        'timeout' => 15,
    );

    $response = wp_remote_get( $url, $args );
    if ( is_wp_error( $response ) ) {
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( intval( $code ) !== 200 ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return false;
    }

    // Try to find an asset named exactly "ai-focused-schema.zip"
    $asset_url = '';
    if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
        foreach ( $data['assets'] as $asset ) {
            if ( isset( $asset['name'] ) && $asset['name'] === 'ai-focused-schema.zip' && ! empty( $asset['browser_download_url'] ) ) {
                $asset_url = $asset['browser_download_url'];
                break;
            }
        }
    }

    // Fallback to the release zipball (note: zipball may not unpack into the exact plugin folder name)
    if ( empty( $asset_url ) && ! empty( $data['zipball_url'] ) ) {
        $asset_url = $data['zipball_url'];
    }

    $version = '';
    if ( ! empty( $data['tag_name'] ) ) {
        $version = ltrim( $data['tag_name'], 'v' );
    } elseif ( ! empty( $data['name'] ) ) {
        $version = $data['name'];
    }

    if ( empty( $version ) || empty( $asset_url ) ) {
        return false;
    }

    $result = array(
        'version'      => $version,
        'download_url' => $asset_url,
    );

    // Cache for 12 hours to avoid rate limits
    set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

    return $result;
}

/**
 * Hook into WP's plugin update check and inject update info when a newer release exists.
 *
 * @param object $transient The update transient object.
 * @return object
 */
function aifs_github_check_for_plugin_update( $transient ) {
    // Nothing to do if transient is empty.
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    // Adjust this to match actual installed plugin path if different.
    $plugin_file = 'ai-focused-schema/ai-focused-schema.php';

    // Only proceed if the plugin is installed (and was checked previously).
    if ( ! isset( $transient->checked[ $plugin_file ] ) ) {
        return $transient;
    }

    $current_version = $transient->checked[ $plugin_file ];
    $remote = aifs_github_get_latest_release();

    if ( ! $remote || empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
        return $transient;
    }

    if ( version_compare( $remote['version'], $current_version, '>' ) ) {
        $update = new stdClass();
        $update->slug = 'ai-focused-schema';
        $update->new_version = $remote['version'];
        $update->url = 'https://github.com/impact2021/ai-focused-schema';
        $update->package = $remote['download_url'];

        $transient->response[ $plugin_file ] = $update;
    }

    return $transient;
}