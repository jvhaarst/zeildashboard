<?php
/**
 * Data validation for API responses
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Validator {

    const VALID_DIRECTIONS = array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' );

    /**
     * Validate wind data from API
     *
     * @param array $data Wind data array
     * @return bool
     */
    public static function validate_wind( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required fields
        if ( ! isset( $data['direction'], $data['speed'], $data['gust'] ) ) {
            return false;
        }

        // Validate direction
        if ( ! in_array( $data['direction'], self::VALID_DIRECTIONS, true ) ) {
            return false;
        }

        // Validate speed and gust are non-negative numbers
        if ( ! is_numeric( $data['speed'] ) || $data['speed'] < 0 ) {
            return false;
        }
        if ( ! is_numeric( $data['gust'] ) || $data['gust'] < 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Validate water level data from API
     *
     * @param array $data Water level data array
     * @return bool
     */
    public static function validate_water_level( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['level'] ) ) {
            return false;
        }

        // Validate level is numeric
        if ( ! is_numeric( $data['level'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Validate current flow data from API
     *
     * @param array $data Current data array
     * @return bool
     */
    public static function validate_current( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['flow_rate'] ) ) {
            return false;
        }

        // Validate flow_rate is numeric and non-negative
        if ( ! is_numeric( $data['flow_rate'] ) || $data['flow_rate'] < 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize wind direction to valid value
     *
     * @param string $direction Raw direction value
     * @return string|null Valid direction or null
     */
    public static function sanitize_direction( $direction ) {
        $direction = strtoupper( trim( $direction ) );

        if ( in_array( $direction, self::VALID_DIRECTIONS, true ) ) {
            return $direction;
        }

        return null;
    }

    /**
     * Sanitize numeric value (wind speed, water level, etc.)
     *
     * @param mixed $value Raw value
     * @return float|null Numeric value or null
     */
    public static function sanitize_number( $value ) {
        $sanitized = floatval( $value );
        return $sanitized;
    }
}
