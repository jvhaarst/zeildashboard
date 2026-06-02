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
		delete_option( 'rsc_current_flow' );
		delete_option( 'rsc_timestamp_current_flow' );
		delete_option( 'rsc_forecast_wind' );
		delete_option( 'rsc_timestamp_forecast_wind' );
		delete_option( 'rsc_forecast_water' );
		delete_option( 'rsc_timestamp_forecast_water' );
		delete_option( 'rsc_last_api_error' );
		delete_option( 'rsc_timestamp_last_api_error' );
	}

	public function test_fetch_current_conditions_success() {
		// Mock successful fetch
		$result = RSC_Fetcher::fetch_current_conditions();
		// Should return true on success
		$this->assertTrue( is_bool( $result ) );
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

	public function test_current_flow_cached_after_fetch() {
		RSC_Fetcher::fetch_current_conditions();
		$flow = RSC_Cache::get( 'current_flow' );
		$this->assertNotFalse( $flow );
		$this->assertIsArray( $flow );
		$this->assertArrayHasKey( 'flow_rate', $flow );
	}

	public function test_fetch_forecast_success() {
		$result = RSC_Fetcher::fetch_forecast();
		$this->assertTrue( is_bool( $result ) );
		$this->assertTrue( $result );
	}

	public function test_forecast_wind_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_wind' );
		$this->assertNotFalse( $forecast );
		$this->assertIsArray( $forecast );
		$this->assertGreater( count( $forecast ), 0 );
	}

	public function test_forecast_water_cached_after_fetch() {
		RSC_Fetcher::fetch_forecast();
		$forecast = RSC_Cache::get( 'forecast_water' );
		$this->assertNotFalse( $forecast );
		$this->assertIsArray( $forecast );
		$this->assertGreater( count( $forecast ), 0 );
	}
}
