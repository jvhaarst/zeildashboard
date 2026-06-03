<?php
/**
 * Fetch data from Open-Meteo (wind) and Rijkswaterstaat DDAPI (water)
 *
 * @package RhineSailingConditions
 * @since 1.0.0
 */

class RSC_Fetcher {

	// Open-Meteo API endpoint (free, no auth required)
	const OPENMETEO_API_URL = 'https://api.open-meteo.com/v1/forecast';

	// Rijkswaterstaat DDAPI observations endpoint (free, no auth required)
	const RWS_API_URL = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

	// Rhine location: Driel boven (complete water measurement suite)
	const LOCATION_LATITUDE  = 51.9397;
	const LOCATION_LONGITUDE = 5.3897;
	const RWS_LOCATION        = 'driel.boven';

	// Shared HTTP settings
	const HTTP_TIMEOUT = 10;
	const USER_AGENT   = 'Rhine-Sailing-Plugin/1.0';

	// m/s -> knots conversion factor
	const MPS_TO_KNOTS = 1.94384;
	// km/h -> knots conversion factor
	const KMH_TO_KNOTS = 0.539957;

	/**
	 * Fetch current wind and water conditions and cache them.
	 *
	 * Wind comes from Open-Meteo; water level, current speed and temperature
	 * are parsed from a single Rijkswaterstaat DDAPI response.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function fetch_current_conditions() {
		$wind = self::fetch_openmeteo_wind();
		if ( ! $wind ) {
			self::log_error( 'Failed to fetch Open-Meteo wind data' );
			return false;
		}

		$water = self::fetch_rws_measurements();
		if ( ! $water ) {
			self::log_error( 'Failed to fetch Rijkswaterstaat measurements' );
			return false;
		}

		// Each value is validated inside its fetcher; cache the validated data.
		RSC_Cache::set( 'current_wind', $wind );
		RSC_Cache::set( 'current_water_level', $water['water_level'] );
		RSC_Cache::set( 'current_speed', $water['current_speed'] );
		RSC_Cache::set( 'current_temperature', $water['temperature'] );

		return true;
	}

	/**
	 * Fetch wind forecast and cache it.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function fetch_forecast() {
		$wind_forecast = self::fetch_openmeteo_wind_forecast();
		if ( ! $wind_forecast ) {
			self::log_error( 'Failed to fetch Open-Meteo wind forecast' );
			return false;
		}

		RSC_Cache::set( 'forecast_wind', $wind_forecast );

		return true;
	}

	/**
	 * Fetch current wind data from Open-Meteo API.
	 *
	 * @return array|false Wind data or false on error
	 */
	private static function fetch_openmeteo_wind() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&current=wind_speed_10m,wind_direction_10m&timezone=Europe/Amsterdam';

		$data = self::http_get_json( $url, 'Open-Meteo wind' );
		if ( false === $data || ! isset( $data['current'] ) ) {
			self::log_error( 'Invalid Open-Meteo response format' );
			return false;
		}

		// Convert km/h to knots.
		$wind_speed_kmh   = floatval( $data['current']['wind_speed_10m'] );
		$wind_speed_knots = $wind_speed_kmh * self::KMH_TO_KNOTS;

		// Convert wind direction (degrees) to 16-point cardinal direction.
		$wind_direction_deg = intval( $data['current']['wind_direction_10m'] );
		$wind_direction     = self::degrees_to_direction( $wind_direction_deg );

		// Estimate gust as 150% of average wind speed (typical ratio).
		$gust_knots = $wind_speed_knots * 1.5;

		$wind_data = array(
			'direction' => $wind_direction,
			'speed'     => round( $wind_speed_knots, 1 ),
			'gust'      => round( $gust_knots, 1 ),
		);

		if ( ! RSC_Validator::validate_wind( $wind_data ) ) {
			self::log_error( 'Wind data validation failed' );
			return false;
		}

		return $wind_data;
	}

	/**
	 * Fetch wind forecast (next 6 hours) from Open-Meteo API.
	 *
	 * @return array|false Forecast array or false on error
	 */
	private static function fetch_openmeteo_wind_forecast() {
		$url = self::OPENMETEO_API_URL . '?latitude=' . self::LOCATION_LATITUDE . '&longitude=' . self::LOCATION_LONGITUDE . '&hourly=wind_speed_10m&forecast_days=1&timezone=Europe/Amsterdam';

		$data = self::http_get_json( $url, 'Open-Meteo forecast' );
		if ( false === $data || ! isset( $data['hourly'], $data['hourly']['wind_speed_10m'] ) ) {
			self::log_error( 'Invalid Open-Meteo forecast response' );
			return false;
		}

		$forecast    = array();
		$wind_speeds = $data['hourly']['wind_speed_10m'];

		for ( $hour = 0; $hour < min( 6, count( $wind_speeds ) ); $hour++ ) {
			$wind_speed_kmh   = floatval( $wind_speeds[ $hour ] );
			$wind_speed_knots = $wind_speed_kmh * self::KMH_TO_KNOTS;

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
	 * Fetch water level, current speed and temperature from the RWS DDAPI.
	 *
	 * A single request returns every measurement for the location, so all
	 * three values are parsed from one response.
	 *
	 * @return array|false Array with 'water_level', 'current_speed' and
	 *                     'temperature' sub-arrays, or false on error.
	 */
	private static function fetch_rws_measurements() {
		$data = self::rws_request();
		if ( false === $data ) {
			return false;
		}

		$waterhoogte  = null; // WATHTE, cm
		$stroomsnelheid = null; // STROOMSHD, m/s
		$temperatuur  = null; // T, °C

		foreach ( $data['WaarnemingenLijst'] as $waarneming ) {
			$code   = $waarneming['AquoMetadata']['Grootheid']['Code'] ?? '';
			$method = $waarneming['AquoMetadata']['WaardeBewerkingsMethode']['Code'] ?? '';
			$value  = $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ?? null;

			if ( null === $value ) {
				continue;
			}

			// Skip 24-hour averages — we want current conditions.
			if ( 'GEM24H' === $method ) {
				continue;
			}

			if ( 'WATHTE' === $code && null === $waterhoogte ) {
				$waterhoogte = floatval( $value );
			} elseif ( 'STROOMSHD' === $code && null === $stroomsnelheid ) {
				$stroomsnelheid = floatval( $value );
			} elseif ( 'T' === $code && null === $temperatuur ) {
				$temperatuur = floatval( $value );
			}
		}

		if ( null === $waterhoogte || null === $stroomsnelheid || null === $temperatuur ) {
			self::log_error( 'Missing one or more RWS measurements (water level, current speed, temperature)' );
			return false;
		}

		$water_level = array(
			'level' => round( $waterhoogte / 100, 2 ), // cm -> m
		);
		$current_speed = array(
			'speed_mps'   => round( $stroomsnelheid, 2 ),
			'speed_knots' => round( $stroomsnelheid * self::MPS_TO_KNOTS, 2 ),
		);
		$temperature = array(
			'celsius' => round( $temperatuur, 1 ),
		);

		if ( ! RSC_Validator::validate_water_level( $water_level ) ) {
			self::log_error( 'Water level validation failed' );
			return false;
		}
		if ( ! RSC_Validator::validate_current_speed( $current_speed ) ) {
			self::log_error( 'Current speed validation failed' );
			return false;
		}
		if ( ! RSC_Validator::validate_temperature( $temperature ) ) {
			self::log_error( 'Temperature validation failed' );
			return false;
		}

		return array(
			'water_level'   => $water_level,
			'current_speed' => $current_speed,
			'temperature'   => $temperature,
		);
	}

	/**
	 * Perform the RWS DDAPI observations request and return the decoded body.
	 *
	 * @return array|false Decoded response with 'WaarnemingenLijst', or false.
	 */
	private static function rws_request() {
		$payload = wp_json_encode( array(
			'locatieLijst'                    => array( self::RWS_LOCATION ),
			'aquoPlusWaarnemingMetadataLijst' => array(
				array(
					'aquoMetadata' => array(
						'messageID' => 1,
					),
				),
			),
		) );

		$args = array(
			'timeout'     => self::HTTP_TIMEOUT,
			'httpversion' => '1.1',
			'user-agent'  => self::USER_AGENT,
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => $payload,
			'method'      => 'POST',
		);

		$response = wp_remote_post( self::RWS_API_URL, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'RWS DDAPI error: ' . $response->get_error_message() );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status ) {
			self::log_error( 'RWS DDAPI returned HTTP ' . $status );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['WaarnemingenLijst'] ) || ! is_array( $data['WaarnemingenLijst'] ) ) {
			self::log_error( 'Invalid RWS DDAPI response format' );
			return false;
		}

		return $data;
	}

	/**
	 * Perform a GET request and return the decoded JSON body.
	 *
	 * @param string $url     Request URL.
	 * @param string $context Short label used in error messages.
	 * @return array|false Decoded JSON array, or false on error.
	 */
	private static function http_get_json( $url, $context ) {
		$args = array(
			'timeout'     => self::HTTP_TIMEOUT,
			'httpversion' => '1.1',
			'user-agent'  => self::USER_AGENT,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_error( $context . ' API error: ' . $response->get_error_message() );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status ) {
			self::log_error( $context . ' returned HTTP ' . $status );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			self::log_error( 'Invalid ' . $context . ' response body' );
			return false;
		}

		return $data;
	}

	/**
	 * Convert degrees to a 16-point Dutch cardinal direction.
	 *
	 * @param int $degrees Wind direction in degrees (0-360).
	 * @return string Cardinal direction (N, NNO, NO, ONO, O, ...).
	 */
	private static function degrees_to_direction( $degrees ) {
		$directions = array( 'N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW' );
		$index      = intval( round( $degrees / 22.5 ) ) % 16;
		return $directions[ $index ];
	}

	/**
	 * Log API errors to the WordPress debug log and the error cache.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'RSC Fetcher Error: ' . $message );
		}
		RSC_Cache::set( 'last_api_error', $message );
	}
}
