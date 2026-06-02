<?php
/**
 * Rhine Sailing Conditions - Real API Test
 * Tests the actual Open-Meteo API integration
 */

// Minimal mock WordPress functions for testing outside WordPress
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$defaults = array(
			'timeout'     => 30,
			'httpversion' => '1.1',
		);
		$args = wp_parse_args( $args, $defaults );

		$context = stream_context_create(
			array(
				'http' => array(
					'timeout' => $args['timeout'],
					'method'  => 'GET',
				),
			)
		);

		$response = @file_get_contents( $url, false, $context );
		if ( $response === false ) {
			return new WP_Error( 'http_request_failed', 'Failed to fetch URL' );
		}
		return array( 'body' => $response );
	}

	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? $response['body'] : '';
	}

	class WP_Error {
		public $errors = array();

		public function __construct( $code = '', $message = '' ) {
			$this->errors[ $code ] = $message;
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = key( $this->errors );
			}
			return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : '';
		}
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$r =& $args;
		} else {
			return $defaults;
		}

		if ( is_array( $defaults ) ) {
			return array_merge( $defaults, $r );
		}
		return $r;
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) );
	}

	function get_option( $option, $default = false ) {
		global $wp_options;
		if ( ! isset( $wp_options ) ) {
			$wp_options = array();
		}
		return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
	}

	function update_option( $option, $value ) {
		global $wp_options;
		if ( ! isset( $wp_options ) ) {
			$wp_options = array();
		}
		$wp_options[ $option ] = $value;
		return true;
	}

	function delete_option( $option ) {
		global $wp_options;
		if ( ! isset( $wp_options ) ) {
			$wp_options = array();
		}
		unset( $wp_options[ $option ] );
		return true;
	}
}

// Include plugin classes
require_once __DIR__ . '/rhine-sailing-conditions/includes/class-cache.php';
require_once __DIR__ . '/rhine-sailing-conditions/includes/class-validator.php';
require_once __DIR__ . '/rhine-sailing-conditions/includes/class-fetcher.php';

// Test output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Rhine Sailing - API Test</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .error { background: #3a1a1a; border-color: #ff6b6b; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>🌊 Rhine Sailing Conditions - Real API Test</h1>
    <p>Testing Open-Meteo API integration for Rhine Arnhem location</p>
";

echo '<div class="test success">';
echo '<div class="title">✓ Test 1: Fetch Current Wind Conditions</div>';

$success = RSC_Fetcher::fetch_current_conditions();
if ( ! $success ) {
	echo '<p style="color: #ff6b6b;">✗ Failed to fetch current conditions</p>';
} else {
	$wind = RSC_Cache::get( 'current_wind' );
	$water_level = RSC_Cache::get( 'current_water_level' );
	$flow = RSC_Cache::get( 'current_flow' );

	echo '<div class="response">';
	echo 'Wind Data:' . "\n";
	echo '  Direction: ' . htmlspecialchars( $wind['direction'] ) . "\n";
	echo '  Speed: ' . htmlspecialchars( $wind['speed'] ) . ' knots' . "\n";
	echo '  Gust: ' . htmlspecialchars( $wind['gust'] ) . ' knots' . "\n";
	echo "\n";
	echo 'Water Level: ' . htmlspecialchars( $water_level['level'] ) . ' m' . "\n";
	echo 'Current Flow: ' . htmlspecialchars( $flow['flow_rate'] ) . ' m³/s' . "\n";
	echo '</div>';
	echo '<p style="color: #51cf66;">✓ Real API data (wind) + mock data (water) fetched successfully!</p>';
}

echo '</div>';

echo '<div class="test success">';
echo '<div class="title">✓ Test 2: Fetch Wind Forecast (6 hours)</div>';

$success = RSC_Fetcher::fetch_forecast();
if ( ! $success ) {
	echo '<p style="color: #ff6b6b;">✗ Failed to fetch forecast</p>';
} else {
	$forecast = RSC_Cache::get( 'forecast_wind' );

	echo '<div class="response">';
	echo 'Wind Forecast:' . "\n";
	foreach ( $forecast as $hour ) {
		echo 'Hour +' . intval( $hour['hour'] ) . ': ' . htmlspecialchars( $hour['speed'] ) . ' knots' . "\n";
	}
	echo '</div>';
	echo '<p style="color: #51cf66;">✓ Forecast data fetched successfully!</p>';
}

echo '</div>';

echo '<div class="test">';
echo '<div class="title">📋 Summary</div>';
echo '<p>The plugin is now using real Open-Meteo API data for wind conditions.</p>';
echo '<p style="color: #74c0fc;">Water level and current data are still using mock values (Rijkswaterstaat API Phase 2).</p>';
echo '</div>';

echo '</body></html>';
?>
