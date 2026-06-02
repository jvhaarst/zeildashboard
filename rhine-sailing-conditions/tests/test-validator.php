<?php
/**
 * Tests for RSC_Validator class
 */

class Test_RSC_Validator extends WP_UnitTestCase {

    public function test_validate_wind_data_valid() {
        $data = array(
            'direction' => 'NE',
            'speed' => 12.5,
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertTrue( $result );
    }

    public function test_validate_wind_data_missing_field() {
        $data = array(
            'direction' => 'NE',
            // missing 'speed'
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_wind_invalid_speed() {
        $data = array(
            'direction' => 'NE',
            'speed' => -5, // negative speed invalid
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_water_level_data() {
        $data = array(
            'level' => 1.45,
        );
        $result = RSC_Validator::validate_water_level( $data );
        $this->assertTrue( $result );
    }

    public function test_validate_current_data() {
        $data = array(
            'flow_rate' => 1.2,
        );
        $result = RSC_Validator::validate_current( $data );
        $this->assertTrue( $result );
    }

    public function test_sanitize_wind_direction() {
        $result = RSC_Validator::sanitize_direction( 'NE' );
        $this->assertContains( $result, array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' ) );
    }
}
