<?php
/**
 * DC Page Cache — early-serve drop-in (wp-content/advanced-cache.php).
 *
 * WordPress includes this file in wp-settings.php when WP_CACHE is on, BEFORE the database,
 * plugins and mu-plugins load. It serves the exact cache files written by the DC Page Cache
 * mu-plugin (same on-disk format: 3-byte status prefix + HTML), so a warm page costs a file
 * read instead of a full WordPress bootstrap. It NEVER stores: storing, purging and every
 * other rule stay with the mu-plugin, which also remains the fallback server whenever this
 * drop-in declines (stale file, bypassed request, missing constant).
 *
 * The bypass rules and the cache-key recipe below intentionally mirror dcpc_bypass() and
 * dcpc_file() in mu-plugins/dc-page-cache.php — including core's wp_is_mobile() test, which
 * the mu-plugin uses for the device variant. Keep the two in sync when either changes.
 */

if ( ! defined( 'ABSPATH' ) ) { return; }

/* Only ever serve a plain front-end GET; everything else falls through to WordPress. */
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { return; }
if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) { return; }
if ( ! isset( $_SERVER['REQUEST_URI'] ) ) { return; }

$dcac_uri = (string) $_SERVER['REQUEST_URI'];
if ( false !== strpos( $dcac_uri, '/wp-' ) || false !== strpos( $dcac_uri, 'preview' ) || false !== strpos( $dcac_uri, 'feed' ) ) { return; }
if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== (string) $_SERVER['QUERY_STRING'] ) { return; }
foreach ( array_keys( $_COOKIE ) as $dcac_c ) {
	if ( preg_match( '/^(wordpress_logged_in|wp-postpass|comment_author)/', (string) $dcac_c ) ) { return; }
}
if ( isset( $_COOKIE['wordpress_test_cookie'] ) ) { return; }

/* Device variant — core wp_is_mobile()'s test, verbatim (the function itself is not loaded yet). */
$dcac_ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
$dcac_mobile   = '' !== $dcac_ua && ( false !== strpos( $dcac_ua, 'Mobile' ) || false !== strpos( $dcac_ua, 'Android' )
	|| false !== strpos( $dcac_ua, 'Silk/' ) || false !== strpos( $dcac_ua, 'Kindle' )
	|| false !== strpos( $dcac_ua, 'BlackBerry' ) || false !== strpos( $dcac_ua, 'Opera Mini' )
	|| false !== strpos( $dcac_ua, 'Opera Mobi' ) );
$dcac_host     = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
$dcac_dir      = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
$dcac_file     = $dcac_dir . '/cache/dc-page/' . md5( $dcac_host . $dcac_uri . ( $dcac_mobile ? '|m' : '|d' ) ) . '.html';
$dcac_ttl      = 1800; /* DCPC_TTL in the mu-plugin: 30 minutes. */

if ( is_readable( $dcac_file ) && ( time() - filemtime( $dcac_file ) ) < $dcac_ttl ) {
	$dcac_head = @file_get_contents( $dcac_file, false, null, 0, 3 );
	if ( '200' === $dcac_head ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Vary: User-Agent' );   /* cache is keyed per device class; proxies must not mix them */
		header( 'X-DC-Cache: hit' );
		$dcac_fp = fopen( $dcac_file, 'rb' );
		if ( $dcac_fp ) {
			fseek( $dcac_fp, 3 );       /* skip the 3-byte status prefix */
			fpassthru( $dcac_fp );
			fclose( $dcac_fp );
		}
		exit;
	}
}
