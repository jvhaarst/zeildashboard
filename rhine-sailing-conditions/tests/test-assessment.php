<?php
/**
 * Tests for RSC_Assessment class.
 *
 * RSC_Assessment is pure PHP (no WordPress dependency): it returns English
 * source-string keys/labels which the display layer translates via __().
 */

class Test_RSC_Assessment extends WP_UnitTestCase {

	public function test_good_conditions() {
		// 8 knots wind (< 10 = "Moderate" band) with light current = good sailing.
		$result = RSC_Assessment::evaluate( 8, 1.0 );
		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'Good conditions for sailing', $result['recommendation'] );
		$this->assertEquals( 'Moderate', $result['wind']['level'] );
		$this->assertEquals( 'Moderate', $result['water']['level'] );
	}

	public function test_insufficient_wind() {
		$result = RSC_Assessment::evaluate( 4, 1.0 );
		$this->assertEquals( 'caution', $result['status'] );
		$this->assertEquals( 'Insufficient wind for good sailing', $result['recommendation'] );
		$this->assertEquals( 'Light', $result['wind']['level'] );
	}

	public function test_wind_too_strong() {
		$result = RSC_Assessment::evaluate( 20, 1.0 );
		$this->assertEquals( 'caution', $result['status'] );
		$this->assertEquals( 'Wind too strong - caution advised', $result['recommendation'] );
		$this->assertEquals( 'Very strong', $result['wind']['level'] );
	}

	public function test_current_too_strong() {
		$result = RSC_Assessment::evaluate( 10, 3.0 );
		$this->assertEquals( 'caution', $result['status'] );
		$this->assertEquals( 'Current too strong - caution advised', $result['recommendation'] );
		$this->assertEquals( 'Very strong', $result['water']['level'] );
	}

	public function test_calm_wind_level() {
		$result = RSC_Assessment::evaluate( 1, 0.2 );
		$this->assertEquals( 'Calm', $result['wind']['level'] );
		$this->assertEquals( 'Weak', $result['water']['level'] );
	}

	public function test_wind_lower_boundary_is_good() {
		// 6 knots is the lower edge of the "good" band (>= 6).
		$result = RSC_Assessment::evaluate( 6, 1.0 );
		$this->assertEquals( 'good', $result['status'] );
	}

	public function test_current_boundary_caution() {
		// current exactly 2.5 is no longer < 2.5, so it trips the strong-current path.
		$result = RSC_Assessment::evaluate( 10, 2.5 );
		$this->assertEquals( 'caution', $result['status'] );
		$this->assertEquals( 'Current too strong - caution advised', $result['recommendation'] );
	}
}
