<?php
/**
 * Tests for RSC_Validator class
 */

class Test_RSC_Validator extends WP_UnitTestCase {

    public function test_validate_wind_data_valid() {
        $data = array(
            'direction' => 'NO',
            'speed' => 12.5,
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertTrue( $result );
    }

    public function test_validate_wind_data_missing_field() {
        $data = array(
            'direction' => 'NO',
            // missing 'speed'
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_wind_invalid_speed() {
        $data = array(
            'direction' => 'NO',
            'speed' => -5, // negative speed invalid
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_wind_invalid_direction() {
        $data = array(
            'direction' => 'XX',
            'speed' => 12.5,
            'gust' => 18.0,
        );
        $result = RSC_Validator::validate_wind( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_wind_not_array() {
        $result = RSC_Validator::validate_wind( 'not an array' );
        $this->assertFalse( $result );
    }

    public function test_validate_wind_invalid_gust() {
        $data = array(
            'direction' => 'NO',
            'speed' => 12.5,
            'gust' => -5,
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

    public function test_validate_water_level_missing_field() {
        $result = RSC_Validator::validate_water_level( array() );
        $this->assertFalse( $result );
    }

    public function test_validate_water_level_non_numeric() {
        $data = array( 'level' => 'not a number' );
        $result = RSC_Validator::validate_water_level( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_water_level_not_array() {
        $result = RSC_Validator::validate_water_level( 'not an array' );
        $this->assertFalse( $result );
    }

    public function test_validate_current_speed_data() {
        $data = array(
            'speed_knots' => 1.2,
            'speed_mps'   => 0.62,
        );
        $result = RSC_Validator::validate_current_speed( $data );
        $this->assertTrue( $result );
    }

    public function test_validate_current_speed_missing_field() {
        $result = RSC_Validator::validate_current_speed( array() );
        $this->assertFalse( $result );
    }

    public function test_validate_current_speed_negative() {
        $data = array( 'speed_knots' => -1.5 );
        $result = RSC_Validator::validate_current_speed( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_current_speed_non_numeric() {
        $data = array( 'speed_knots' => 'fast' );
        $result = RSC_Validator::validate_current_speed( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_temperature_data() {
        $data = array( 'celsius' => 18.5 );
        $result = RSC_Validator::validate_temperature( $data );
        $this->assertTrue( $result );
    }

    public function test_validate_temperature_missing_field() {
        $result = RSC_Validator::validate_temperature( array() );
        $this->assertFalse( $result );
    }

    public function test_validate_temperature_non_numeric() {
        $data = array( 'celsius' => 'warm' );
        $result = RSC_Validator::validate_temperature( $data );
        $this->assertFalse( $result );
    }

    public function test_validate_temperature_out_of_range() {
        $data = array( 'celsius' => 99 );
        $result = RSC_Validator::validate_temperature( $data );
        $this->assertFalse( $result );
    }

    public function test_sanitize_wind_direction_valid_uppercase() {
        $result = RSC_Validator::sanitize_direction( 'NO' );
        $this->assertEquals( 'NO', $result );
    }

    public function test_sanitize_wind_direction_valid_lowercase() {
        $result = RSC_Validator::sanitize_direction( 'no' );
        $this->assertEquals( 'NO', $result );
    }

    public function test_sanitize_wind_direction_with_whitespace() {
        $result = RSC_Validator::sanitize_direction( '  ZW  ' );
        $this->assertEquals( 'ZW', $result );
    }

    public function test_sanitize_wind_direction_invalid() {
        $result = RSC_Validator::sanitize_direction( 'XX' );
        $this->assertNull( $result );
    }

    public function test_sanitize_wind_direction_empty() {
        $result = RSC_Validator::sanitize_direction( '' );
        $this->assertNull( $result );
    }

    public function test_sanitize_number_valid_float() {
        $result = RSC_Validator::sanitize_number( 12.5 );
        $this->assertEquals( 12.5, $result );
    }

    public function test_sanitize_number_string_number() {
        $result = RSC_Validator::sanitize_number( '12.5' );
        $this->assertEquals( 12.5, $result );
    }

    public function test_sanitize_number_zero() {
        $result = RSC_Validator::sanitize_number( 0 );
        $this->assertEquals( 0.0, $result );
    }

    public function test_sanitize_number_negative() {
        $result = RSC_Validator::sanitize_number( -5.5 );
        $this->assertEquals( -5.5, $result );
    }
}
