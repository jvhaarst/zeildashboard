<?php
/**
 * Tests for RSC_Fetcher class.
 *
 * HTTP is intercepted via the `pre_http_request` filter and served from the
 * JSON fixtures in tests/fixtures/, so these run offline and deterministically
 * — no live calls to Open-Meteo or Rijkswaterstaat.
 */

class Test_RSC_Fetcher extends WP_UnitTestCase {

	/** @var array Raw decoded fixtures, for computing expected values. */
	private $fixtures = array();

	public function setUp(): void {
		parent::setUp();

		$dir = __DIR__ . '/fixtures/';
		$this->fixtures = array(
			'wind'     => file_get_contents( $dir . 'openmeteo-wind.json' ),
			'forecast' => file_get_contents( $dir . 'openmeteo-forecast.json' ),
			'rws'      => file_get_contents( $dir . 'rws-driel-boven.json' ),
		);

		add_filter( 'pre_http_request', array( $this, 'serve_fixture' ), 10, 3 );

		foreach ( array( 'current_wind', 'current_water_level', 'current_speed', 'current_temperature', 'forecast_wind', 'forecast_precipitation', 'last_api_error' ) as $key ) {
			RSC_Cache::delete( $key );
		}
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'serve_fixture' ), 10 );
		foreach ( array( 'current_wind', 'current_water_level', 'current_speed', 'current_temperature', 'forecast_wind', 'forecast_precipitation' ) as $key ) {
			RSC_Cache::delete( $key );
		}
		parent::tearDown();
	}

	/**
	 * Route each outbound request to the matching fixture.
	 *
	 * @param false  $preempt Short-circuit value (false to proceed normally).
	 * @param array  $args    Request args.
	 * @param string $url     Request URL.
	 * @return array WP HTTP response array.
	 */
	public function serve_fixture( $preempt, $args, $url ) {
		if ( false !== strpos( $url, 'rijkswaterstaat' ) ) {
			$body = $this->fixtures['rws'];
		} elseif ( false !== strpos( $url, 'hourly=' ) ) {
			$body = $this->fixtures['forecast'];
		} else {
			$body = $this->fixtures['wind'];
		}

		return array(
			'body'     => $body,
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	public function test_fetch_current_conditions_success() {
		$this->assertTrue( RSC_Fetcher::fetch_current_conditions() );
	}

	public function test_current_conditions_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$wind = RSC_Cache::get( 'current_wind' );
		$this->assertIsArray( $wind );
		$this->assertArrayHasKey( 'direction', $wind );
		$this->assertArrayHasKey( 'speed', $wind );
		$this->assertArrayHasKey( 'gust', $wind );
	}

	public function test_wind_uses_real_measured_gust() {
		RSC_Fetcher::fetch_current_conditions();
		$wind = RSC_Cache::get( 'current_wind' );

		$raw       = json_decode( $this->fixtures['wind'], true );
		$gust_kmh  = (float) $raw['current']['wind_gusts_10m'];
		$expected  = round( $gust_kmh * 0.539957, 1 );

		// Gust comes from wind_gusts_10m, not the 1.5x speed estimate.
		$this->assertEqualsWithDelta( $expected, $wind['gust'], 0.05 );
	}

	public function test_water_level_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$water_level = RSC_Cache::get( 'current_water_level' );
		$this->assertIsArray( $water_level );
		$this->assertArrayHasKey( 'level', $water_level );
	}

	public function test_current_speed_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$speed = RSC_Cache::get( 'current_speed' );
		$this->assertIsArray( $speed );
		$this->assertArrayHasKey( 'speed_knots', $speed );
		$this->assertArrayHasKey( 'speed_mps', $speed );
	}

	public function test_temperature_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$temperature = RSC_Cache::get( 'current_temperature' );
		$this->assertIsArray( $temperature );
		$this->assertArrayHasKey( 'celsius', $temperature );
	}

	public function test_fetch_forecast_success() {
		$this->assertTrue( RSC_Fetcher::fetch_forecast() );
	}

	public function test_forecast_wind_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_wind' );
		$this->assertIsArray( $forecast );
		$this->assertGreaterThan( 0, count( $forecast ) );
		$this->assertLessThanOrEqual( 6, count( $forecast ) );
	}

	public function test_forecast_precipitation_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_precipitation' );
		$this->assertIsArray( $forecast );
		$this->assertArrayHasKey( 'precipitation', $forecast[0] );
		$this->assertArrayHasKey( 'hour', $forecast[0] );
	}

	public function test_fetch_returns_false_when_request_fails() {
		// Override the fixture filter with one that simulates an API outage.
		remove_filter( 'pre_http_request', array( $this, 'serve_fixture' ), 10 );
		add_filter( 'pre_http_request', function () {
			return new WP_Error( 'http_request_failed', 'simulated outage' );
		} );

		$this->assertFalse( RSC_Fetcher::fetch_current_conditions() );
	}

	public function test_all_conditions_cached_together() {
		RSC_Fetcher::fetch_current_conditions();
		$this->assertIsArray( RSC_Cache::get( 'current_wind' ) );
		$this->assertIsArray( RSC_Cache::get( 'current_water_level' ) );
		$this->assertIsArray( RSC_Cache::get( 'current_speed' ) );
		$this->assertIsArray( RSC_Cache::get( 'current_temperature' ) );
	}
}
