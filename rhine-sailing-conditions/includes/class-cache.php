<?php
/**
 * Cache wrapper around WordPress options
 */

class RSC_Cache {

    /**
     * Store data in WordPress options
     *
     * @param string $key Option key
     * @param mixed  $data Data to store
     * @return bool
     */
    public static function set( $key, $data ) {
        $option_key = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;

        update_option( $option_key, $data );
        update_option( $timestamp_key, time() );

        return true;
    }

    /**
     * Retrieve data from WordPress options
     *
     * @param string $key Option key
     * @return mixed Data or false if not found
     */
    public static function get( $key ) {
        $option_key = 'rsc_' . $key;
        $data = get_option( $option_key, false );
        return $data;
    }

    /**
     * Get timestamp of when data was cached
     *
     * @param string $key Option key
     * @return int Timestamp or 0 if not found
     */
    public static function get_timestamp( $key ) {
        $timestamp_key = 'rsc_timestamp_' . $key;
        $timestamp = get_option( $timestamp_key, 0 );
        return intval( $timestamp );
    }

    /**
     * Check if cached data is stale
     *
     * @param string $key Option key
     * @param int    $ttl Time-to-live in seconds
     * @return bool True if stale, false otherwise
     */
    public static function is_stale( $key, $ttl = 3600 ) {
        $timestamp = self::get_timestamp( $key );
        if ( $timestamp === 0 ) {
            return true;
        }
        return ( time() - $timestamp ) > $ttl;
    }

    /**
     * Delete cached data
     *
     * @param string $key Option key
     * @return bool
     */
    public static function delete( $key ) {
        $option_key = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;

        delete_option( $option_key );
        delete_option( $timestamp_key );

        return true;
    }
}
