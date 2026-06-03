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
	 * Fetch water level from RWS DDAPI (Rijkswaterstaat actual measurements)
	 * Retrieves "Waterhoogte" (water height) in cm from official RWS service
	 *
	 * @return array|false Water level data or false on error
	 */
	private static function fetch_rijkswaterstaat_water_level() {
		$url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

		$payload = wp_json_encode( array(
			'locatieLijst'                    => array( 'arnhem.nederrijn' ),
			'aquoPlusWaarnemingMetadataLijst' => array(
				array(
					'aquoMetadata' => array(
						'messageID' => 1,
					),
				),
			),
		) );

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => $payload,
			'method'      => 'POST',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'RWS DDAPI error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['WaarnemingenLijst'] ) ) {
			self::log_error( 'Invalid RWS DDAPI response format' );
			return false;
		}

		// Find water height ("Waterhoogte") measurement
		$waterhoogte = null;
		foreach ( $data['WaarnemingenLijst'] as $waarneming ) {
			if ( isset( $waarneming['AquoMetadata']['Grootheid']['Code'] ) &&
				 'WATHTE' === $waarneming['AquoMetadata']['Grootheid']['Code'] ) {

				// Found water height measurement
				if ( isset( $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ) ) {
					$waterhoogte = floatval( $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] );
					break;
				}
			}
		}

		if ( null === $waterhoogte ) {
			self::log_error( 'No water height measurement found in RWS response' );
			return false;
		}

		// Convert cm to meters
		$level_meters = $waterhoogte / 100;

		$water_data = array(
			'level' => round( $level_meters, 2 ),
		);

		// Validate before returning
		if ( ! RSC_Validator::validate_water_level( $water_data ) ) {
			self::log_error( 'Water level validation failed' );
			return false;
		}

		return $water_data;
	}

	/**
	 * Fetch current discharge from RWS DDAPI (Rijkswaterstaat actual measurements)
	 * Retrieves "Debiet" (discharge) in m³/s from official RWS service
	 *
	 * @return array|false Current discharge data or false on error
	 */
	private static function fetch_rijkswaterstaat_current() {
		$url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

		$payload = wp_json_encode( array(
			'locatieLijst'                    => array( 'arnhem.nederrijn' ),
			'aquoPlusWaarnemingMetadataLijst' => array(
				array(
					'aquoMetadata' => array(
						'messageID' => 1,
					),
				),
			),
		) );

		$args = array(
			'timeout'     => 10,
			'httpversion' => '1.1',
			'user-agent'  => 'Rhine-Sailing-Plugin/1.0',
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => $payload,
			'method'      => 'POST',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'RWS DDAPI error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['WaarnemingenLijst'] ) ) {
			self::log_error( 'Invalid RWS DDAPI response format' );
			return false;
		}

		// Find discharge ("Debiet") measurement (prefer non-24h average for current conditions)
		$debiet = null;
		foreach ( $data['WaarnemingenLijst'] as $waarneming ) {
			if ( isset( $waarneming['AquoMetadata']['Grootheid']['Code'] ) &&
				 'Q' === $waarneming['AquoMetadata']['Grootheid']['Code'] ) {

				// Skip 24-hour averages, use current measurement
				$bewer_methode = $waarneming['AquoMetadata']['WaardeBewerkingsMethode']['Code'] ?? '';
				if ( 'GEM24H' !== $bewer_methode ) {
					// Found discharge measurement
					if ( isset( $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ) ) {
						$debiet = floatval( $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] );
						break;
					}
				}
			}
		}

		if ( null === $debiet ) {
			self::log_error( 'No discharge measurement found in RWS response' );
			return false;
		}

		$flow_data = array(
			'flow_rate' => round( $debiet, 2 ),
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
