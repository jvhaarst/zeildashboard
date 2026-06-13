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
        // Self-heal: refresh on render if the cache has gone stale (e.g. when
        // WP-Cron has not run on a low-traffic site). Guarded by a lock inside.
        RSC_Fetcher::maybe_refresh();

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

        $html  = '<div class="rsc-dashboard">';
        $html .= self::render_header();

        if ( RSC_Cache::is_stale( 'current_wind', self::STALE_TTL ) ) {
            $html .= '<div class="rsc-stale">' . esc_html__( 'Warning: this data may be outdated.', self::TEXT_DOMAIN ) . '</div>';
        }

        // Recommendation eyecatcher (needs both wind and current speed).
        if ( $wind && $current_speed ) {
            $html .= self::render_recommendation( $wind, $current_speed );
        }

        $html .= '<div class="rsc-grid">';

        if ( $wind ) {
            $html .= self::render_wind( $wind );
        }
        if ( $water_level || $current_speed || $temperature ) {
            $html .= self::render_water( $water_level, $current_speed, $temperature );
        }
        if ( $wind && $current_speed ) {
            $html .= self::render_assessment( $wind, $current_speed );
        }
        if ( $wind_forecast || $precip_forecast ) {
            $html .= self::render_forecast_card( $wind_forecast, $precip_forecast );
        }

        $html .= '</div>'; // .rsc-grid
        $html .= '</div>'; // .rsc-dashboard

        return $html;
    }

    /**
     * Render dashboard header (title + last-update line).
     *
     * @return string HTML
     */
    private static function render_header() {
        $last_update = self::get_last_update_time();
        return '<div class="rsc-header">
            <h3 class="rsc-title">' . esc_html__( 'Rhine Sailing Conditions', self::TEXT_DOMAIN ) . '</h3>
            <p class="rsc-updated">' . esc_html__( 'Updated', self::TEXT_DOMAIN ) . ' ' . esc_html( $last_update ) . '</p>
        </div>';
    }

    /**
     * Render the recommendation "eyecatcher" card.
     *
     * @param array $wind          Wind data array.
     * @param array $current_speed Current speed data array.
     * @return string HTML
     */
    private static function render_recommendation( $wind, $current_speed ) {
        $wind_knots    = isset( $wind['speed'] ) ? (float) $wind['speed'] : 0;
        $current_knots = isset( $current_speed['speed_knots'] ) ? (float) $current_speed['speed_knots'] : 0;

        $assessment = RSC_Assessment::evaluate( $wind_knots, $current_knots );
        $badge_class = ( 'good' === $assessment['status'] ) ? 'rsc-badge-good' : 'rsc-badge-caution';

        // Compact at-a-glance verdicts.
        if ( $wind_knots < 6 ) {
            $wind_verdict = __( 'Too weak', self::TEXT_DOMAIN );
        } elseif ( $wind_knots < 15 ) {
            $wind_verdict = __( 'Good', self::TEXT_DOMAIN );
        } else {
            $wind_verdict = __( 'Too strong', self::TEXT_DOMAIN );
        }
        $current_verdict = ( $current_knots < 2.5 ) ? __( 'Safe', self::TEXT_DOMAIN ) : __( 'Strong', self::TEXT_DOMAIN );

        return '<div class="rsc-reco-card">
            <h4 class="rsc-reco-title">' . esc_html__( 'Sailing recommendation', self::TEXT_DOMAIN ) . '</h4>
            <div class="rsc-reco-row">
                <span class="rsc-badge ' . $badge_class . '">' . esc_html( self::translate( $assessment['recommendation'] ) ) . '</span>
                <div class="rsc-chips">
                    <div class="rsc-chip">
                        <span class="rsc-chip-label">' . esc_html__( 'Wind for sailing', self::TEXT_DOMAIN ) . '</span>
                        <span class="rsc-chip-value">' . esc_html( $wind_verdict ) . '</span>
                    </div>
                    <div class="rsc-chip">
                        <span class="rsc-chip-label">' . esc_html__( 'Current speed', self::TEXT_DOMAIN ) . '</span>
                        <span class="rsc-chip-value">' . esc_html( $current_verdict ) . '</span>
                    </div>
                </div>
            </div>
            <p class="rsc-reco-hint">' . esc_html__( 'Ideal conditions: 6-15 knots wind + current under 2.5 knots', self::TEXT_DOMAIN ) . '</p>
        </div>';
    }

    /**
     * Render wind data card.
     *
     * @param array $wind Wind data array
     * @return string HTML
     */
    private static function render_wind( $wind ) {
        $direction = isset( $wind['direction'] ) ? esc_html( $wind['direction'] ) : '—';
        $speed     = isset( $wind['speed'] ) ? esc_html( $wind['speed'] ) : '—';
        $gust      = isset( $wind['gust'] ) ? esc_html( $wind['gust'] ) : null;
        $beaufort  = isset( $wind['speed'] ) ? esc_html( self::knots_to_beaufort( $wind['speed'] ) ) : '—';

        $html  = '<div class="rsc-card">';
        $html .= '<h4 class="rsc-card-title">' . esc_html__( 'Wind conditions', self::TEXT_DOMAIN ) . '</h4>';

        $html .= '<div class="rsc-metric">
            <div class="rsc-label">' . esc_html__( 'Wind speed', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-value">' . $speed . ' <span class="rsc-unit">kn</span></div>
            <div class="rsc-sub">' . esc_html__( 'Wind force', self::TEXT_DOMAIN ) . ' ' . $beaufort . ' Bft</div>
        </div>';

        $html .= '<div class="rsc-metric">
            <div class="rsc-label">' . esc_html__( 'Direction', self::TEXT_DOMAIN ) . '</div>
            <div class="rsc-direction">' . $direction . '</div>
        </div>';

        if ( null !== $gust ) {
            $html .= '<div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Wind gusts', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value">' . $gust . ' <span class="rsc-unit">kn</span></div>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the combined water card (level, current speed, temperature).
     *
     * @param array|false $water_level   Water level data or false.
     * @param array|false $current_speed Current speed data or false.
     * @param array|false $temperature   Temperature data or false.
     * @return string HTML
     */
    private static function render_water( $water_level, $current_speed, $temperature ) {
        $html  = '<div class="rsc-card">';
        $html .= '<h4 class="rsc-card-title">' . esc_html__( 'Water conditions', self::TEXT_DOMAIN ) . '</h4>';

        if ( $water_level ) {
            $level = isset( $water_level['level'] ) ? esc_html( $water_level['level'] ) : '—';
            $html .= '<div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Water level', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value">' . $level . ' <span class="rsc-unit">m NAP</span></div>
            </div>';
        }

        if ( $current_speed ) {
            $knots = isset( $current_speed['speed_knots'] ) ? esc_html( $current_speed['speed_knots'] ) : '—';
            $mps   = isset( $current_speed['speed_mps'] ) ? esc_html( $current_speed['speed_mps'] ) : null;
            $sub   = ( null !== $mps ) ? '<div class="rsc-sub">' . $mps . ' m/s</div>' : '';
            $html .= '<div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Current speed', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value">' . $knots . ' <span class="rsc-unit">kn</span></div>
                ' . $sub . '
            </div>';
        }

        if ( $temperature ) {
            $celsius = isset( $temperature['celsius'] ) ? esc_html( $temperature['celsius'] ) : '—';
            $html .= '<div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Water temperature', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value">' . $celsius . ' <span class="rsc-unit">°C</span></div>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the qualitative assessment card.
     *
     * @param array $wind          Wind data array.
     * @param array $current_speed Current speed data array.
     * @return string HTML
     */
    private static function render_assessment( $wind, $current_speed ) {
        $wind_knots    = isset( $wind['speed'] ) ? (float) $wind['speed'] : 0;
        $current_knots = isset( $current_speed['speed_knots'] ) ? (float) $current_speed['speed_knots'] : 0;
        $assessment    = RSC_Assessment::evaluate( $wind_knots, $current_knots );

        return '<div class="rsc-card">
            <h4 class="rsc-card-title">' . esc_html__( 'Current assessment', self::TEXT_DOMAIN ) . '</h4>
            <div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Wind level', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value rsc-value-sm">' . esc_html( self::translate( $assessment['wind']['level'] ) ) . '</div>
                <div class="rsc-sub">' . esc_html( self::translate( $assessment['wind']['description'] ) ) . '</div>
            </div>
            <div class="rsc-metric">
                <div class="rsc-label">' . esc_html__( 'Water current', self::TEXT_DOMAIN ) . '</div>
                <div class="rsc-value rsc-value-sm">' . esc_html( self::translate( $assessment['water']['level'] ) ) . '</div>
                <div class="rsc-sub">' . esc_html( self::translate( $assessment['water']['description'] ) ) . '</div>
            </div>
        </div>';
    }

    /**
     * Render the combined wind + precipitation forecast card.
     *
     * @param array|false $wind_forecast   Wind forecast list or false.
     * @param array|false $precip_forecast Precipitation forecast list or false.
     * @return string HTML
     */
    private static function render_forecast_card( $wind_forecast, $precip_forecast ) {
        // Index precipitation by hour so we can line it up with the wind hours.
        $precip_by_hour = array();
        if ( is_array( $precip_forecast ) ) {
            foreach ( $precip_forecast as $point ) {
                if ( isset( $point['hour'] ) ) {
                    $precip_by_hour[ (int) $point['hour'] ] = $point;
                }
            }
        }

        $hours = is_array( $wind_forecast ) ? $wind_forecast : array();

        $html  = '<div class="rsc-card rsc-forecast-card">';
        $html .= '<h4 class="rsc-card-title">' . esc_html__( 'Forecast (next 6 hours)', self::TEXT_DOMAIN ) . '</h4>';
        $html .= '<div class="rsc-forecast-grid">';

        foreach ( $hours as $point ) {
            $hour_num = isset( $point['hour'] ) ? (int) $point['hour'] : 0;
            $speed    = isset( $point['speed'] ) ? esc_html( $point['speed'] ) : '—';
            $beaufort = isset( $point['speed'] ) ? esc_html( self::knots_to_beaufort( $point['speed'] ) ) : '—';

            $html .= '<div class="rsc-forecast-cell">
                <div class="rsc-forecast-hour">+' . esc_html( $hour_num ) . 'u</div>
                <div class="rsc-forecast-line">' . $speed . ' kn</div>
                <div class="rsc-forecast-sub">' . $beaufort . ' Bft</div>';

            if ( isset( $precip_by_hour[ $hour_num ] ) ) {
                $p  = $precip_by_hour[ $hour_num ];
                $mm = isset( $p['precipitation'] ) ? esc_html( $p['precipitation'] ) : '—';
                $html .= '<div class="rsc-forecast-line rsc-forecast-rain">' . $mm . ' mm</div>';
                if ( isset( $p['probability'] ) && null !== $p['probability'] ) {
                    $html .= '<div class="rsc-forecast-sub">' . esc_html( $p['probability'] ) . '%</div>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
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
     * Translate a dynamic English source string from RSC_Assessment.
     *
     * The strings come from RSC_Assessment at runtime, so xgettext cannot see
     * them as literals here — register_strings() below lists them as literal
     * __() calls so the .pot/.po stay in sync.
     *
     * @param string $string English source string.
     * @return string Translated string.
     */
    private static function translate( $string ) {
        return __( $string, self::TEXT_DOMAIN );
    }

    /**
     * No-op string registry so `xgettext` extracts the dynamic assessment
     * strings translated via self::translate(). Never called at runtime.
     *
     * @return void
     */
    public static function register_strings() {
        // Wind / current levels.
        __( 'Calm', self::TEXT_DOMAIN );
        __( 'Light', self::TEXT_DOMAIN );
        __( 'Moderate', self::TEXT_DOMAIN );
        __( 'Strong', self::TEXT_DOMAIN );
        __( 'Very strong', self::TEXT_DOMAIN );
        __( 'Weak', self::TEXT_DOMAIN );
        // Descriptions.
        __( 'No wind', self::TEXT_DOMAIN );
        __( 'Light breeze', self::TEXT_DOMAIN );
        __( 'Nice breeze', self::TEXT_DOMAIN );
        __( 'Strong wind', self::TEXT_DOMAIN );
        __( 'Dangerous conditions', self::TEXT_DOMAIN );
        __( 'Very weak current', self::TEXT_DOMAIN );
        __( 'Light current', self::TEXT_DOMAIN );
        __( 'Average current', self::TEXT_DOMAIN );
        __( 'Strong current', self::TEXT_DOMAIN );
        __( 'Very strong current', self::TEXT_DOMAIN );
        // Recommendations.
        __( 'Good conditions for sailing', self::TEXT_DOMAIN );
        __( 'Insufficient wind for good sailing', self::TEXT_DOMAIN );
        __( 'Wind too strong - caution advised', self::TEXT_DOMAIN );
        __( 'Current too strong - caution advised', self::TEXT_DOMAIN );
        __( 'Check conditions before sailing', self::TEXT_DOMAIN );
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
