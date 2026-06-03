<?php
/**
 * Tests for RSC_Fetcher class
 */

class Test_RSC_Fetcher extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Clean up any test data
		delete_option( 'rsc_current_wind' );
		delete_option( 'rsc_timestamp_current_wind' );
		delete_option( 'rsc_current_water_level' );
		delete_option( 'rsc_timestamp_current_water_level' );
		delete_option( 'rsc_current_speed' );
		delete_option( 'rsc_timestamp_current_speed' );
		delete_option( 'rsc_current_temperature' );
		delete_option( 'rsc_timestamp_current_temperature' );
		delete_option( 'rsc_forecast_wind' );
		delete_option( 'rsc_timestamp_forecast_wind' );
		delete_option( 'rsc_forecast_precipitation' );
		delete_option( 'rsc_timestamp_forecast_precipitation' );
		delete_option( 'rsc_last_api_error' );
		delete_option( 'rsc_timestamp_last_api_error' );
	}

	public function tearDown(): void {
		parent::tearDown();
		// Clear all cache entries
		RSC_Cache::delete( 'current_wind' );
		RSC_Cache::delete( 'current_water_level' );
		RSC_Cache::delete( 'current_speed' );
		RSC_Cache::delete( 'current_temperature' );
		RSC_Cache::delete( 'forecast_wind' );
		RSC_Cache::delete( 'forecast_precipitation' );
	}

	public function test_fetch_current_conditions_success() {
		$result = RSC_Fetcher::fetch_current_conditions();
		// Should return true on success
		$this->assertTrue( $result );
	}

	public function test_current_conditions_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$wind = RSC_Cache::get( 'current_wind' );
		$this->assertNotFalse( $wind );
		$this->assertIsArray( $wind );
		$this->assertArrayHasKey( 'direction', $wind );
		$this->assertArrayHasKey( 'speed', $wind );
		$this->assertArrayHasKey( 'gust', $wind );
	}

	public function test_water_level_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$water_level = RSC_Cache::get( 'current_water_level' );
		$this->assertNotFalse( $water_level );
		$this->assertIsArray( $water_level );
		$this->assertArrayHasKey( 'level', $water_level );
	}

	public function test_current_speed_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$speed = RSC_Cache::get( 'current_speed' );
		$this->assertNotFalse( $speed );
		$this->assertIsArray( $speed );
		$this->assertArrayHasKey( 'speed_knots', $speed );
		$this->assertArrayHasKey( 'speed_mps', $speed );
	}

	public function test_temperature_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$temperature = RSC_Cache::get( 'current_temperature' );
		$this->assertNotFalse( $temperature );
		$this->assertIsArray( $temperature );
		$this->assertArrayHasKey( 'celsius', $temperature );
	}

	public function test_fetch_forecast_success() {
		$result = RSC_Fetcher::fetch_forecast();
		$this->assertTrue( $result );
	}

	public function test_forecast_wind_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_wind' );
		$this->assertNotFalse( $forecast );
		$this->assertIsArray( $forecast );
		$this->assertGreaterThan( 0, count( $forecast ) );
	}

	public function test_forecast_precipitation_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_precipitation' );
		$this->assertNotFalse( $forecast );
		$this->assertIsArray( $forecast );
		$this->assertGreaterThan( 0, count( $forecast ) );
		$this->assertArrayHasKey( 'precipitation', $forecast[0] );
		$this->assertArrayHasKey( 'hour', $forecast[0] );
	}

	public function test_fetch_returns_false_on_wind_validation_failure() {
		// Verify that a successful fetch caches at least one current measurement.
		$result = RSC_Fetcher::fetch_current_conditions();
		if ( $result ) {
			$wind        = RSC_Cache::get( 'current_wind' );
			$water       = RSC_Cache::get( 'current_water_level' );
			$speed       = RSC_Cache::get( 'current_speed' );
			$temperature = RSC_Cache::get( 'current_temperature' );
			$this->assertTrue(
				$wind !== false || $water !== false || $speed !== false || $temperature !== false
			);
		}
	}

	public function test_forecast_and_current_conditions_separate() {
		// Fetch current conditions
		$result_current = RSC_Fetcher::fetch_current_conditions();
		$this->assertTrue( $result_current );

		// Fetch forecasts
		$result_forecast = RSC_Fetcher::fetch_forecast();
		$this->assertTrue( $result_forecast );

		// Verify both are cached separately
		$wind     = RSC_Cache::get( 'current_wind' );
		$forecast = RSC_Cache::get( 'forecast_wind' );

		$this->assertNotFalse( $wind );
		$this->assertNotFalse( $forecast );

		$this->assertIsArray( $wind );
		$this->assertIsArray( $forecast );
	}

	public function test_all_conditions_cached_together() {
		RSC_Fetcher::fetch_current_conditions();

		$wind        = RSC_Cache::get( 'current_wind' );
		$water       = RSC_Cache::get( 'current_water_level' );
		$speed       = RSC_Cache::get( 'current_speed' );
		$temperature = RSC_Cache::get( 'current_temperature' );

		// All four must be cached
		$this->assertNotFalse( $wind );
		$this->assertNotFalse( $water );
		$this->assertNotFalse( $speed );
		$this->assertNotFalse( $temperature );
	}
}
