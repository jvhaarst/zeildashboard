<?php
/**
 * Cache wrapper around WordPress options
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Cache {

    /**
     * Store data in WordPress options
     *
     * @param string $key Option key (alphanumeric/underscore)
     * @param mixed  $data Data to store (arrays, strings, numbers, booleans)
     * @return bool True if the key was valid and writes were attempted.
     */
    public static function set( $key, $data ) {
        $key = sanitize_key( $key );
        if ( empty( $key ) ) {
            return false;
        }

        $option_key    = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;

        // Write both independently. update_option() returns false when the
        // stored value is unchanged, which is not an error — so we must not
        // short-circuit the timestamp write behind the data write.
        //
        // autoload is 'no': this data churns every 15-30 min and is only read
        // on pages with the shortcode, so it should not load on every request.
        update_option( $option_key, $data, false );
        update_option( $timestamp_key, time(), false );

        return true;
    }

    /**
     * Retrieve data from WordPress options
     *
     * @param string $key Option key (alphanumeric/underscore)
     * @return mixed Data or false if not found
     */
    public static function get( $key ) {
        $key = sanitize_key( $key );
        if ( empty( $key ) ) {
            return false;
        }

        $option_key = 'rsc_' . $key;
        $data = get_option( $option_key, false );
        return $data;
    }

    /**
     * Get timestamp of when data was cached
     *
     * @param string $key Option key (alphanumeric/underscore)
     * @return int Timestamp or 0 if not found
     */
    public static function get_timestamp( $key ) {
        $key = sanitize_key( $key );
        if ( empty( $key ) ) {
            return 0;
        }

        $timestamp_key = 'rsc_timestamp_' . $key;
        $timestamp = get_option( $timestamp_key, 0 );
        return intval( $timestamp );
    }

    /**
     * Check if cached data is stale
     *
     * @param string $key Option key (alphanumeric/underscore)
     * @param int    $ttl Time-to-live in seconds
     * @return bool True if stale, false otherwise
     */
    public static function is_stale( $key, $ttl = 3600 ) {
        $key = sanitize_key( $key );
        if ( empty( $key ) ) {
            return true;
        }

        $timestamp = self::get_timestamp( $key );
        if ( $timestamp === 0 ) {
            return true;
        }
        return ( time() - $timestamp ) > $ttl;
    }

    /**
     * Delete cached data
     *
     * @param string $key Option key (alphanumeric/underscore)
     * @return bool True if both options deleted, false otherwise
     */
    public static function delete( $key ) {
        $key = sanitize_key( $key );
        if ( empty( $key ) ) {
            return false;
        }

        $option_key = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;

        return delete_option( $option_key ) && delete_option( $timestamp_key );
    }
}
