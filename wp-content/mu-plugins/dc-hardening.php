<?php
/**
 * Plugin Name: DC Hardening
 * Description: Small, reversible hardening for the Dales & Cobbles site: the public REST user
 *              directory requires a login (it handed out account ids/slugs to anonymous
 *              scanners), and XML-RPC is off (Apache also denies the file; this covers any
 *              request that still reaches WordPress). Nothing here changes what readers see.
 * Version: 1.0.0
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }

/* REST /wp/v2/users (list + by-id) anonymous → 401. /users/me and every authenticated
   request are untouched, so wp-admin and Site Kit keep working. Opt back out with the
   DC_PUBLIC_REST_USERS=1 environment variable if a future integration needs it. */
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result ) { return $result; }
	if ( '1' === (string) getenv( 'DC_PUBLIC_REST_USERS' ) ) { return $result; }
	$route = $request->get_route();
	if ( preg_match( '#^/wp/v2/users($|/\d+$)#', $route ) && ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
	}
	return $result;
}, 10, 3 );

/* No Jetpack/app-password XML-RPC consumers on this site; publishing is wp-admin + wp-cli. */
add_filter( 'xmlrpc_enabled', '__return_false' );
