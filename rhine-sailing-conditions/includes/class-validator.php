<?php
/**
 * Data validation for API responses
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Validator {

    // 16-point Dutch compass directions.
    const VALID_DIRECTIONS = array( 'N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW' );

    // Plausible water temperature range (°C) for the Rhine.
    const MIN_TEMPERATURE = -5;
    const MAX_TEMPERATURE = 40;

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
     * Validate current speed data from API
     *
     * @param array $data Current speed data array (speed_knots required)
     * @return bool
     */
    public static function validate_current_speed( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['speed_knots'] ) ) {
            return false;
        }

        // Validate speed is numeric and non-negative
        if ( ! is_numeric( $data['speed_knots'] ) || $data['speed_knots'] < 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Validate water temperature data from API
     *
     * @param array $data Temperature data array (celsius required)
     * @return bool
     */
    public static function validate_temperature( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['celsius'] ) ) {
            return false;
        }

        // Validate temperature is numeric and within a plausible range
        if ( ! is_numeric( $data['celsius'] ) ) {
            return false;
        }
        if ( $data['celsius'] < self::MIN_TEMPERATURE || $data['celsius'] > self::MAX_TEMPERATURE ) {
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
