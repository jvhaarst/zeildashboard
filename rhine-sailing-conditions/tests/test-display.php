<?php
/**
 * Tests for RSC_Display class
 */

class Test_RSC_Display extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Set up cache with mock data
        RSC_Cache::set( 'current_wind', array( 'direction' => 'NO', 'speed' => 12, 'gust' => 18 ) );
        RSC_Cache::set( 'current_water_level', array( 'level' => 1.45 ) );
        RSC_Cache::set( 'current_speed', array( 'speed_knots' => 1.2, 'speed_mps' => 0.62 ) );
        RSC_Cache::set( 'current_temperature', array( 'celsius' => 18.5 ) );
        RSC_Cache::set( 'forecast_wind', array( array( 'hour' => 0, 'speed' => 12 ) ) );
    }

    public function tearDown(): void {
        parent::tearDown();
        RSC_Cache::delete( 'current_wind' );
        RSC_Cache::delete( 'current_water_level' );
        RSC_Cache::delete( 'current_speed' );
        RSC_Cache::delete( 'current_temperature' );
        RSC_Cache::delete( 'forecast_wind' );
    }

    public function test_shortcode_returns_html() {
        $output = RSC_Display::render_shortcode( array() );
        $this->assertStringContainsString( 'rsc-dashboard', $output );
        $this->assertStringContainsString( 'NO', $output );
        $this->assertStringContainsString( '12', $output );
    }

    public function test_shortcode_handles_missing_data() {
        RSC_Cache::delete( 'current_wind' );
        RSC_Cache::delete( 'current_water_level' );
        RSC_Cache::delete( 'current_speed' );
        RSC_Cache::delete( 'current_temperature' );

        $output = RSC_Display::render_shortcode( array() );
        // Assert on the language-neutral error class rather than translated text.
        $this->assertStringContainsString( 'rsc-error', $output );
    }

    public function test_format_wind_speed() {
        $formatted = RSC_Display::format_wind_speed( 12.5 );
        $this->assertStringContainsString( '12.5', $formatted );
        $this->assertStringContainsString( 'kn', $formatted );
    }

    public function test_format_water_level() {
        $formatted = RSC_Display::format_water_level( 1.45 );
        $this->assertStringContainsString( '1.45', $formatted );
        $this->assertStringContainsString( 'm', $formatted );
    }

    public function test_knots_to_beaufort() {
        $this->assertEquals( 0, RSC_Display::knots_to_beaufort( 0.5 ) );
        $this->assertEquals( 1, RSC_Display::knots_to_beaufort( 2 ) );
        $this->assertEquals( 2, RSC_Display::knots_to_beaufort( 4 ) );
        $this->assertEquals( 4, RSC_Display::knots_to_beaufort( 12 ) );
        $this->assertEquals( 8, RSC_Display::knots_to_beaufort( 35 ) );
        $this->assertEquals( 12, RSC_Display::knots_to_beaufort( 70 ) );
    }

    public function test_wind_renders_beaufort() {
        $output = RSC_Display::render_shortcode( array() );
        $this->assertStringContainsString( 'Bft', $output );
    }

    public function test_shortcode_output_contains_css_class() {
        $output = RSC_Display::render_shortcode( array() );
        $this->assertStringContainsString( 'class=', $output );
        $this->assertStringContainsString( 'rsc-', $output );
    }

    public function test_shortcode_escapes_output() {
        RSC_Cache::set( 'current_wind', array( 'direction' => '<script>alert("xss")</script>', 'speed' => 12, 'gust' => 18 ) );
        $output = RSC_Display::render_shortcode( array() );
        // Should not contain unescaped script tag
        $this->assertStringNotContainsString( '<script>', $output );
    }
}
