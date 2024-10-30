<?php
namespace WordPressdotorg\Experiments\Helpers;

/**
 * Set/Store a value in a static variable, and return it.
 *
 * @param string $key   The key to store the value under.
 * @param mixed  $value The value to store. If left as null, returns the value.
 * @return mixed The stored value.
 */
function static_store( $key, $value = null ) {
	static $store = [];
	if ( null === $value ) {
		return $store[ $key ] ?? null;
	}

	return $store[ $key ] = $value;
}