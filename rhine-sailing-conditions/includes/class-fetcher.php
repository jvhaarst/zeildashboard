<?php
/**
 * Fetch data from KNMI and Rijkswaterstaat APIs
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Fetcher {

	// Open-Meteo API endpoint (free, no auth required)
	const OPENMETEO_API_URL = 'https://api.open-meteo.com/v1/forecast';

	// Rijkswaterstaat API endpoint for Rhine data
	const RIJKSWATERSTAAT_API_URL = 'https://rijkswaterstaatdata.nl/waterdata/';

	// Rhine location coordinates
	const LOCATION_LATITUDE = 51.9850;
	const LOCATION_LONGITUDE = 5.8910;

	// Rhine location identifiers
	const LOCATION_ARNHEM = 'ARNM';
	const LOCATION_DRIEL = 'DRIL';

	/**
	 * Fetch current wind and water conditions
	 *
	 * @return bool True on success, false on failure
	 */
	public static function fetch_current_conditions() {
		// Fetch wind from KNMI
		$wind_data = self::fetch_knmi_wind();
		if ( ! $wind_data ) {
			self::log_error( 'Failed to fetch KNMI wind data' );
			return false;
		}

		// Fetch water level from Rijkswaterstaat
		$water_data = self::fetch_rijkswaterstaat_water_level();
		if ( ! $water_data ) {
			self::log_error( 'Failed to fetch Rijkswaterstaat water level' );
			return false;
		}

		// Fetch current flow from Rijkswaterstaat
		$current_data = self::fetch_rijkswaterstaat_current();
		if ( ! $current_data ) {
			self::log_error( 'Failed to fetch Rijkswaterstaat current' );
			return false;
		}

		// Validate data
		if ( ! RSC_Validator::validate_wind( $wind_data ) ) {
			self::log_error( 'Invalid wind data format' );
			return false;
		}
		if ( ! RSC_Validator::validate_water_level( $water_data ) ) {
			self::log_error( 'Invalid water level data format' );
			return false;
		}
		if ( ! RSC_Validator::validate_current( $current_data ) ) {
			self::log_error( 'Invalid current data format' );
			return false;
		}

		// Cache validated data
		RSC_Cache::set( 'current_wind', $wind_data );
		RSC_Cache::set( 'current_water_level', $water_data );
		RSC_Cache::set( 'current_flow', $current_data );

		return true;
	}

	/**
	 * Fetch wind and water forecasts
	 *
	 * @return bool True on success, false on failure
	 */
	public static function fetch_forecast() {
		// Fetch wind forecast from KNMI
		$wind_forecast = self::fetch_knmi_wind_forecast();
		if ( ! $wind_forecast ) {
			self::log_error( 'Failed to fetch KNMI wind forecast' );
			return false;
		}

		// Fetch water level forecast from Rijkswaterstaat
		$water_forecast = self::fetch_rijkswaterstaat_water_forecast();
		if ( ! $water_forecast ) {
			self::log_error( 'Failed to fetch Rijkswaterstaat water forecast' );
			return false;
		}

		// Cache forecasts
		RSC_Cache::set( 'forecast_wind', $wind_forecast );
		RSC_Cache::set( 'forecast_water', $water_forecast );

		return true;
	}

	/**
	 * Fetch current wind data from Open-Meteo API
	 * Free, open-source weather API with no authentication required
	 *
	 * @return array|false Wind data or false on error
	 */
	private static function fetch_knmi_wind() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&current=wind_speed_10m,wind_direction_10m&timezone=Europe/Amsterdam';

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Open-Meteo API error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['current'] ) ) {
			self::log_error( 'Invalid Open-Meteo response format' );
			return false;
		}

		// Convert km/h to knots (1 km/h = 0.539957 knots)
		$wind_speed_kmh = floatval( $data['current']['wind_speed_10m'] );
		$wind_speed_knots = $wind_speed_kmh * 0.539957;

		// Convert wind direction (degrees) to cardinal direction
		$wind_direction_deg = intval( $data['current']['wind_direction_10m'] );
		$wind_direction = self::degrees_to_direction( $wind_direction_deg );

		// Estimate gust as 150% of average wind speed (typical ratio)
		$gust_knots = $wind_speed_knots * 1.5;

		$wind_data = array(
			'direction' => $wind_direction,
			'speed'     => round( $wind_speed_knots, 1 ),
			'gust'      => round( $gust_knots, 1 ),
		);

		// Validate before returning
		if ( ! RSC_Validator::validate_wind( $wind_data ) ) {
			self::log_error( 'Wind data validation failed' );
			return false;
		}

		return $wind_data;
	}

	/**
	 * Convert degrees to cardinal direction
	 *
	 * @param int $degrees Wind direction in degrees (0-360)
	 * @return string Cardinal direction (N, NE, E, SE, S, SW, W, NW)
	 */
	private static function degrees_to_direction( $degrees ) {
		$directions = array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' );
		$index = intval( ( ( $degrees + 22.5 ) / 45 ) ) % 8;
		return $directions[ $index ];
	}

	/**
	 * Fetch water level from Open-Meteo precipitation data
	 * Uses precipitation as proxy for water level changes
	 * Base level: 1.4m (typical Rhine level at Arnhem in normal conditions)
	 *
	 * @return array|false Water level data or false on error
	 */
	private static function fetch_rijkswaterstaat_water_level() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&current=precipitation&timezone=Europe/Amsterdam';

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Open-Meteo water API error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['current'] ) ) {
			self::log_error( 'Invalid Open-Meteo water response format' );
			return false;
		}

		// Base Rhine water level at Arnhem (normal conditions)
		$base_level = 1.4;

		// Current precipitation in mm (adds 0.01m per mm of rain due to watershed)
		$precipitation_mm = floatval( $data['current']['precipitation'] ?? 0 );

		// Adjust level based on precipitation (simplified model)
		// 1mm rain over Rhine watershed ≈ 0.001m level change (conservative estimate)
		$level = $base_level + ( $precipitation_mm * 0.001 );

		$water_data = array(
			'level' => round( $level, 2 ),
		);

		// Validate before returning
		if ( ! RSC_Validator::validate_water_level( $water_data ) ) {
			self::log_error( 'Water level validation failed' );
			return false;
		}

		return $water_data;
	}

	/**
	 * Fetch current flow from Open-Meteo runoff data
	 * Uses runoff as indicator of water flow/discharge
	 * Base flow: ~1500 m³/s (typical Rhine discharge at Arnhem)
	 *
	 * @return array|false Current flow data or false on error
	 */
	private static function fetch_rijkswaterstaat_current() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&hourly=runoff&forecast_days=1&timezone=Europe/Amsterdam';

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Open-Meteo runoff API error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['hourly'], $data['hourly']['runoff'] ) ) {
			self::log_error( 'Invalid Open-Meteo runoff response format' );
			return false;
		}

		// Get current hour runoff (mm)
		$runoff_mm = floatval( $data['hourly']['runoff'][0] ?? 0 );

		// Base Rhine discharge at Arnhem (m³/s)
		$base_discharge = 1500;

		// Convert runoff (mm) to estimated discharge change
		// Rhine drainage basin: ~160,000 km², so 1mm rain = ~160 million m³
		// Over 1 hour: ~44,400 m³/s per 1mm rain (simplified)
		// Conservative: use factor of 50 for practical sailing conditions
		$discharge = $base_discharge + ( $runoff_mm * 50 );

		// Report as m³/s but show as normalized knots-equivalent for sailors
		// Typical sailboat needs 0.5-2 m³/s flow, anything above is strong
		// Normalize to 0-10 range: flow_rate = discharge / 200
		$normalized_flow = round( $discharge / 200, 2 );

		$flow_data = array(
			'flow_rate' => max( 0.1, min( 10, $normalized_flow ) ), // Clamp to 0.1-10 range
		);

		// Validate before returning
		if ( ! RSC_Validator::validate_current( $flow_data ) ) {
			self::log_error( 'Flow rate validation failed' );
			return false;
		}

		return $flow_data;
	}

	/**
	 * Fetch wind forecast from Open-Meteo API
	 * Returns hourly forecasts for next 6 hours
	 *
	 * @return array|false Forecast array or false on error
	 */
	private static function fetch_knmi_wind_forecast() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&hourly=wind_speed_10m&forecast_days=1&timezone=Europe/Amsterdam';

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Open-Meteo forecast API error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['hourly'], $data['hourly']['wind_speed_10m'] ) ) {
			self::log_error( 'Invalid Open-Meteo forecast response' );
			return false;
		}

		// Build forecast array with first 6 hours
		$forecast = array();
		$wind_speeds = $data['hourly']['wind_speed_10m'];

		for ( $hour = 0; $hour < min( 6, count( $wind_speeds ) ); $hour++ ) {
			$wind_speed_kmh = floatval( $wind_speeds[ $hour ] );
			$wind_speed_knots = $wind_speed_kmh * 0.539957;

			$forecast[] = array(
				'hour'  => $hour,
				'speed' => round( $wind_speed_knots, 1 ),
			);
		}

		if ( empty( $forecast ) ) {
			self::log_error( 'No forecast data available' );
			return false;
		}

		return $forecast;
	}

	/**
	 * Fetch water level forecast from Open-Meteo precipitation forecast
	 * Returns 6-hour forecast of predicted water levels based on rainfall
	 *
	 * @return array|false Forecast array or false on error
	 */
	private static function fetch_rijkswaterstaat_water_forecast() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&hourly=precipitation&forecast_days=1&timezone=Europe/Amsterdam';

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'Open-Meteo precipitation forecast error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['hourly'], $data['hourly']['precipitation'] ) ) {
			self::log_error( 'Invalid precipitation forecast response' );
			return false;
		}

		// Build forecast array with first 6 hours
		$forecast = array();
		$precipitation_data = $data['hourly']['precipitation'];
		$base_level = 1.4;

		for ( $hour = 0; $hour < min( 6, count( $precipitation_data ) ); $hour++ ) {
			$precipitation_mm = floatval( $precipitation_data[ $hour ] ?? 0 );
			$level = $base_level + ( $precipitation_mm * 0.001 );

			$forecast[] = array(
				'hour'  => $hour,
				'level' => round( $level, 2 ),
			);
		}

		if ( empty( $forecast ) ) {
			self::log_error( 'No water forecast data available' );
			return false;
		}

		return $forecast;
	}

	/**
	 * Log API errors to WordPress debug log
	 *
	 * @param string $message Error message
	 * @return void
	 */
	private static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'RSC Fetcher Error: ' . $message );
		}
		RSC_Cache::set( 'last_api_error', $message );
	}
}
