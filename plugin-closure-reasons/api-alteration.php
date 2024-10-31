<?php
namespace WordPressdotorg\Experiments\PluginClosureReasons;
use function WordPressdotorg\Experiments\Helpers\static_store as store;

/**
 * Intercept the request to the plugin update check API, and redirect it to v1.2 of the same API.
 *
 * @param object $filter_var The value to return instead of the result.
 * @param array  $parsed_args HTTP request arguments.
 * @param string $url         The request URL.
 * @return object The altered request.
 */
function pre_http_request( $filter_var, $parsed_args, $url ) {
	$match_urls = [
		'https://api.wordpress.org/plugins/update-check/1.1/',
		'http://api.wordpress.org/plugins/update-check/1.1/'
	];
	if ( ! in_array( $url, $match_urls, true ) ) {
		return $filter_var;
	}

	// Upgrade version, retaining all other URL pieces.
	$url = str_replace( '/1.1/', '/1.2/', $url );

	// This field isn't needed anymore.
	unset( $parsed_args['body']['all'] );

	// Alter the returned data when it's stored into the transient.
	add_filter( 'pre_set_site_transient_update_plugins', __NAMESPACE__ . '\alter_transient' );

	$request = wp_remote_post( $url, $parsed_args );

	// Store the result for use by alter_transient().
	store( 'plugins_api_result', $request );

	return $request;
}
add_filter( 'pre_http_request', __NAMESPACE__ . '\pre_http_request', 10, 3 );

/**
 * Alter the update_plugins transient to contain the closed plugins, and to match the expected 1.1 format for autoupdates.
 *
 * @param object $value The transient value.
 * @return object The altered transient value.
 */
function alter_transient( $value ) {
	// If the transient isn't all there, or is already altered, nothing needs to be done.
	if ( ! $value || ! isset( $value->response ) || isset( $value->closed ) ) {
		return $value;
	}

	// The 1.2 API returns auto-updates differently, shoehorn it back into the current expected format.
	array_walk( $value->response, function( &$item ) {
		if ( ! isset( $item->autoupdate ) || ! is_array( $item->autoupdate ) ) {
			return;
		}

		// Overwrite.
		$item = (object) array_merge(
			(array) $item,
			(array) reset( $item->autoupdate ),
			[ 'autoupdate' => true ],
		);
	} );

	// Add the closed plugins into the transient.
	$value->closed = [];

	$request = store( 'plugins_api_result' );
	if ( empty( $request ) || is_wp_error( $request ) ) {
		return $value;
	}

	// Fetch the raw JSON.
	$request = json_decode( wp_remote_retrieve_body( $request ) );

	if ( ! empty( $request->closed ) ) {
		$value->closed = (array) $request->closed;
	}
	
	return $value;
}
