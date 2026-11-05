<?php
/**
 * Plugin Name: DC Page Cache
 * Description: Tiny full-page cache for the Dales & Cobbles site. Serves anonymous HTML visitors
 *              from disk (per URL + phone/desktop, because ad units are sized per device), stores at
 *              shutdown, purges on publish. Logged-in users, commenters and editors always bypass.
 * Version: 1.0.0
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }

define( 'DCPC_TTL', 30 * MINUTE_IN_SECONDS );
define( 'DCPC_DIR', WP_CONTENT_DIR . '/cache/dc-page' );
define( 'DCPC_MAXFILES', 400 );

/* ---------- helpers ---------- */

function dcpc_is_mobile() {
	if ( function_exists( 'wp_is_mobile' ) ) { return wp_is_mobile(); }
	return isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '#Mobile|Android|iPhone|iPod#i', (string) $_SERVER['HTTP_USER_AGENT'] );
}

/* Requests we must never serve from or store into the cache. */
function dcpc_bypass() {
	if ( 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { return true; }
	if ( is_admin() || defined( 'WP_ADMIN' ) ) { return true; }
	$uri = (string) $_SERVER['REQUEST_URI'];
	if ( false !== strpos( $uri, '/wp-' ) || false !== strpos( $uri, 'preview' ) || false !== strpos( $uri, 'feed' ) ) { return true; }
	if ( '' !== (string) $_SERVER['QUERY_STRING'] ) { return true; }
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( preg_match( '/^(wordpress_logged_in|wp-postpass|comment_author)/', $c ) ) { return true; }
	}
	if ( isset( $_COOKIE['wordpress_test_cookie'] ) ) { return true; }   /* someone just logged out / login page visitor */
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) { return true; }
	return false;
}

function dcpc_file() {
	$uri  = (string) $_SERVER['REQUEST_URI'];
	$host = strtolower( (string) $_SERVER['HTTP_HOST'] );
	$key  = $host . $uri . ( dcpc_is_mobile() ? '|m' : '|d' );
	return DCPC_DIR . '/' . md5( $key ) . '.html';
}

/* ---------- serve (earliest possible point in the bootstrap) ---------- */

add_action( 'muplugins_loaded', function () {
	if ( dcpc_bypass() ) { return; }
	$f = dcpc_file();
	if ( is_readable( $f ) && ( time() - filemtime( $f ) ) < DCPC_TTL ) {
		$status = (int) substr( (string) @file_get_contents( $f, false, null, 0, 3 ), 0, 3 );
		if ( 200 !== $status ) { return; }
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Vary: User-Agent' );   /* cache is keyed per device class; proxies must not mix them */
		header( 'X-DC-Cache: hit' );
		$fp = fopen( $f, 'rb' );
		fseek( $fp, 3 );               /* skip the 3-byte status prefix */
		fpassthru( $fp );
		fclose( $fp );
		exit;
	}
}, 0 );

/* ---------- store ---------- */

add_action( 'template_redirect', function () {
	if ( dcpc_bypass() ) { return; }
	ob_start( function ( $html ) {
		$status = http_response_code();
		if ( 200 !== $status || '' === $html ) { return $html; }
		$ct = '';
		foreach ( headers_list() as $h ) { if ( 0 === stripos( $h, 'Content-Type:' ) ) { $ct = $h; break; } }
		if ( false === stripos( $ct, 'text/html' ) ) { return $html; }
		if ( is_user_logged_in() || isset( $_COOKIE['wordpress_logged_in_' . COOKIEHASH] ) ) { return $html; }
		dcpc_store( $html );
		return $html;
	} );
}, 0 );

function dcpc_store( $html ) {
	if ( ! is_dir( DCPC_DIR ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.mkdir_error_suppression -- cache dir under wp-content.
		@mkdir( DCPC_DIR, 0755, true );
	}
	if ( ! is_writable( DCPC_DIR ) ) { return; }
	/* Evict oldest files when the cache grows past the cap. */
	$files = glob( DCPC_DIR . '/*.html' );
	if ( is_array( $files ) && count( $files ) >= DCPC_MAXFILES ) {
		usort( $files, function ( $a, $b ) { return filemtime( $a ) - filemtime( $b ); } );
		foreach ( array_slice( $files, 0, 40 ) as $old ) { @unlink( $old ); }
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache write.
	@file_put_contents( dcpc_file(), '200' . $html, LOCK_EX );
}

/* ---------- purge ---------- */

function dcpc_purge_all() {
	$files = glob( DCPC_DIR . '/*.html' );
	if ( is_array( $files ) ) { foreach ( $files as $f ) { @unlink( $f ); } }
}

/* Any publish/update/trash: clear everything (the catalogue is small; a fresh
   page is rebuilt on the next visit, invisibly to the visitor). */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'post' !== $post->post_type ) { return; }
	if ( $new === $old ) { return; }
	dcpc_purge_all();
}, 10, 3 );
