<?php
/**
 * Render shortcode display
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Display {

    /**
     * Render the [rhine-sailing-conditions] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode( $atts = array() ) {
        // Get cached data
        $wind = RSC_Cache::get( 'current_wind' );
        $water_level = RSC_Cache::get( 'current_water_level' );
        $flow = RSC_Cache::get( 'current_flow' );
        $wind_forecast = RSC_Cache::get( 'forecast_wind' );

        // Check if we have any data
        if ( ! $wind && ! $water_level && ! $flow ) {
            return '<div class="rsc-error">Current conditions unavailable. Please try again later.</div>';
        }

        // Build HTML output
        $html = '<div class="rsc-dashboard">';
        $html .= self::render_header();
        $html .= '<div class="rsc-container">';
        $html .= '<div class="rsc-current">';

        if ( $wind ) {
            $html .= self::render_wind( $wind );
        }
        if ( $water_level ) {
            $html .= self::render_water_level( $water_level );
        }
        if ( $flow ) {
            $html .= self::render_flow( $flow );
        }

        $html .= '</div>'; // .rsc-current

        if ( $wind_forecast ) {
            $html .= '<div class="rsc-forecast">';
            $html .= self::render_forecast( $wind_forecast );
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
            <h3>Rhine Sailing Conditions</h3>
            <p class="rsc-updated">Updated ' . esc_html( $last_update ) . '</p>
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
        $speed = isset( $wind['speed'] ) ? esc_html( $wind['speed'] ) : '—';
        $gust = isset( $wind['gust'] ) ? esc_html( $wind['gust'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">Wind</div>
            <div class="rsc-value">' . $speed . ' kts ' . $direction . '</div>
            <div class="rsc-sub">Gust: ' . $gust . ' kts</div>
        </div>';
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
            <div class="rsc-label">Water Level</div>
            <div class="rsc-value">' . $level . ' m</div>
        </div>';
    }

    /**
     * Render flow/current data
     *
     * @param array $flow Current flow array
     * @return string HTML
     */
    private static function render_flow( $flow ) {
        $flow_rate = isset( $flow['flow_rate'] ) ? esc_html( $flow['flow_rate'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">Current</div>
            <div class="rsc-value">' . $flow_rate . ' m³/s</div>
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

        $html = '<div class="rsc-forecast-header">
            <h4>Next 6 Hours</h4>
        </div>';
        $html .= '<div class="rsc-forecast-chart">';

        foreach ( $forecast as $hour ) {
            $speed = isset( $hour['speed'] ) ? esc_html( $hour['speed'] ) : '—';
            $hour_num = isset( $hour['hour'] ) ? esc_html( $hour['hour'] ) : '—';
            $html .= '<div class="rsc-forecast-point">
                <div class="rsc-forecast-hour">+' . $hour_num . 'h</div>
                <div class="rsc-forecast-speed">' . $speed . '</div>
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
        return number_format( $speed, 1 ) . ' kts';
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
            return 'Never';
        }

        $diff = time() - $timestamp;

        if ( $diff < 60 ) {
            return 'just now';
        } elseif ( $diff < 3600 ) {
            $minutes = floor( $diff / 60 );
            return $minutes . ' minute' . ( $minutes > 1 ? 's' : '' ) . ' ago';
        } else {
            $hours = floor( $diff / 3600 );
            return $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
        }
    }
}
