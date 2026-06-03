<?php
/**
 * Render shortcode display
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Display {

    // Text domain for translations.
    const TEXT_DOMAIN = 'rhine-sailing-conditions';

    // Data is considered stale after this many seconds.
    const STALE_TTL = 3600;

    /**
     * Render the [rhine-sailing-conditions] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode( $atts = array() ) {
        // Get cached data
        $wind          = RSC_Cache::get( 'current_wind' );
        $water_level   = RSC_Cache::get( 'current_water_level' );
        $current_speed = RSC_Cache::get( 'current_speed' );
        $temperature   = RSC_Cache::get( 'current_temperature' );
        $wind_forecast = RSC_Cache::get( 'forecast_wind' );
        $precip_forecast = RSC_Cache::get( 'forecast_precipitation' );

        // Check if we have any data
        if ( ! $wind && ! $water_level && ! $current_speed && ! $temperature ) {
            return '<div class="rsc-error">' . esc_html__( 'Current conditions are unavailable. Please try again later.', self::TEXT_DOMAIN ) . '</div>';
        }

        // Build HTML output
        $html  = '<div class="rsc-dashboard">';
        $html .= self::render_header();

        if ( RSC_Cache::is_stale( 'current_wind', self::STALE_TTL ) ) {
            $html .= '<div class="rsc-stale">' . esc_html__( 'Warning: this data may be outdated.', self::TEXT_DOMAIN ) . '</div>';
        }

        $html .= '<div class="rsc-container">';
        $html .= '<div class="rsc-current">';

        if ( $wind ) {
            $html .= self::render_wind( $wind );
        }
        if ( $water_level ) {
            $html .= self::render_water_level( $water_level );
        }
        if ( $current_speed ) {
            $html .= self::render_current_speed( $current_speed );
        }
        if ( $temperature ) {
            $html .= self::render_temperature( $temperature );
        }

        $html .= '</div>'; // .rsc-current

        if ( $wind_forecast || $precip_forecast ) {
            $html .= '<div class="rsc-forecast">';
            if ( $wind_forecast ) {
                $html .= self::render_forecast( $wind_forecast );
            }
            if ( $precip_forecast ) {
                $html .= self::render_precipitation_forecast( $precip_forecast );
            }
            $html .= '</div>'; // .rsc-forecast
        }

        $html .= '</div>'; // .rsc-container
        $html .= '</div>'; // .rsc-dashboard

        return $html;
    }

    /**
     * Render dashboard header
     *
     * @return string HTML
     */
    private static function render_header() {
        $last_update = self::get_last_update_time();
        return '<div class="rsc-header">
            <h3>' . esc_html__( 'Rhine Sailing Conditions', self::TEXT_DOMAIN ) . '</h3>
            <p class="rsc-updated">' . esc_html__( 'Updated', self::TEXT_DOMAIN ) . ' ' . esc_html( $last_update ) . '</p>
        </div>';
    }

    /**
     * Render wind data
     *
     * @param array $wind Wind data array
     * @return string HTML
     */
    private static function render_wind( $wind ) {
        $direction = isset( $wind['direction'] ) ? esc_html( $wind['direction'] ) : '—';
        $speed     = isset( $wind['speed'] ) ? esc_html( $wind['speed'] ) : '—';
        $beaufort  = isset( $wind['speed'] ) ? esc_html( self::knots_to_beaufort( $wind['speed'] ) ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">' . esc_html__( 'Wind', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-value">' . $speed . ' kn ' . $direction . '</div>
            <div class="rsc-sub">' . esc_html__( 'Wind force:', self::TEXT_DOMAIN ) . ' ' . $beaufort . ' Bft</div>
        </div>';
    }

    /**
     * Convert a sustained wind speed in knots to the Beaufort wind-force scale.
     *
     * @param float $knots Sustained wind speed in knots.
     * @return int Beaufort force (0-12).
     */
    public static function knots_to_beaufort( $knots ) {
        // Lower bound (in knots) of Beaufort forces 1 through 12.
        $lower_bounds = array( 1, 4, 7, 11, 17, 22, 28, 34, 41, 48, 56, 64 );
        $force        = 0;
        foreach ( $lower_bounds as $index => $min_knots ) {
            if ( $knots >= $min_knots ) {
                $force = $index + 1;
            }
        }
        return $force;
    }

    /**
     * Render water level data
     *
     * @param array $water_level Water level array
     * @return string HTML
     */
    private static function render_water_level( $water_level ) {
        $level = isset( $water_level['level'] ) ? esc_html( $water_level['level'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">' . esc_html__( 'Water level', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-value">' . $level . ' m NAP</div>
        </div>';
    }

    /**
     * Render current speed data
     *
     * @param array $current_speed Current speed array
     * @return string HTML
     */
    private static function render_current_speed( $current_speed ) {
        $knots = isset( $current_speed['speed_knots'] ) ? esc_html( $current_speed['speed_knots'] ) : '—';
        $mps   = isset( $current_speed['speed_mps'] ) ? esc_html( $current_speed['speed_mps'] ) : null;

        $sub = '';
        if ( null !== $mps ) {
            $sub = '<div class="rsc-sub">' . $mps . ' m/s</div>';
        }

        return '<div class="rsc-condition">
            <div class="rsc-label">' . esc_html__( 'Current speed', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-value">' . $knots . ' kn</div>
            ' . $sub . '
        </div>';
    }

    /**
     * Render water temperature data
     *
     * @param array $temperature Temperature array
     * @return string HTML
     */
    private static function render_temperature( $temperature ) {
        $celsius = isset( $temperature['celsius'] ) ? esc_html( $temperature['celsius'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">' . esc_html__( 'Water temperature', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-value">' . $celsius . ' °C</div>
        </div>';
    }

    /**
     * Render wind forecast
     *
     * @param array $forecast Forecast data array
     * @return string HTML
     */
    private static function render_forecast( $forecast ) {
        if ( empty( $forecast ) || ! is_array( $forecast ) ) {
            return '';
        }

        $html  = '<div class="rsc-forecast-header">
            <h4>' . esc_html__( 'Wind (next 6 hours)', self::TEXT_DOMAIN ) . '</h4>
        </div>';
        $html .= '<div class="rsc-forecast-chart">';

        foreach ( $forecast as $hour ) {
            $speed    = isset( $hour['speed'] ) ? esc_html( $hour['speed'] ) : '—';
            $hour_num = isset( $hour['hour'] ) ? esc_html( $hour['hour'] ) : '—';
            $html    .= '<div class="rsc-forecast-point">
                <div class="rsc-forecast-hour">+' . $hour_num . 'u</div>
                <div class="rsc-forecast-speed">' . $speed . ' kn</div>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render precipitation forecast
     *
     * @param array $forecast Precipitation forecast data array
     * @return string HTML
     */
    private static function render_precipitation_forecast( $forecast ) {
        if ( empty( $forecast ) || ! is_array( $forecast ) ) {
            return '';
        }

        $html  = '<div class="rsc-forecast-header">
            <h4>' . esc_html__( 'Precipitation (next 6 hours)', self::TEXT_DOMAIN ) . '</h4>
        </div>';
        $html .= '<div class="rsc-forecast-chart">';

        foreach ( $forecast as $hour ) {
            $mm       = isset( $hour['precipitation'] ) ? esc_html( $hour['precipitation'] ) : '—';
            $hour_num = isset( $hour['hour'] ) ? esc_html( $hour['hour'] ) : '—';
            $prob     = ( isset( $hour['probability'] ) && null !== $hour['probability'] )
                ? '<div class="rsc-forecast-hour">' . esc_html( $hour['probability'] ) . '%</div>'
                : '';
            $html    .= '<div class="rsc-forecast-point">
                <div class="rsc-forecast-hour">+' . $hour_num . 'u</div>
                <div class="rsc-forecast-speed">' . $mm . ' mm</div>
                ' . $prob . '
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format wind speed with units
     *
     * @param float $speed Wind speed in knots
     * @return string Formatted speed
     */
    public static function format_wind_speed( $speed ) {
        return number_format( $speed, 1 ) . ' kn';
    }

    /**
     * Format water level with units
     *
     * @param float $level Water level in meters
     * @return string Formatted level
     */
    public static function format_water_level( $level ) {
        return number_format( $level, 2 ) . ' m';
    }

    /**
     * Get human-readable last update time
     *
     * @return string Last update time description
     */
    private static function get_last_update_time() {
        $timestamp = RSC_Cache::get_timestamp( 'current_wind' );

        if ( $timestamp === 0 ) {
            return __( 'never', self::TEXT_DOMAIN );
        }

        $diff = time() - $timestamp;

        if ( $diff < 60 ) {
            return __( 'just now', self::TEXT_DOMAIN );
        } elseif ( $diff < 3600 ) {
            $minutes = (int) floor( $diff / 60 );
            /* translators: %d: number of minutes. */
            return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, self::TEXT_DOMAIN ), $minutes );
        } else {
            $hours = (int) floor( $diff / 3600 );
            /* translators: %d: number of hours. */
            return sprintf( _n( '%d hour ago', '%d hours ago', $hours, self::TEXT_DOMAIN ), $hours );
        }
    }
}
