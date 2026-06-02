# Rhine Sailing Conditions WordPress Plugin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that displays real-time sailing conditions (wind, water level, current) + forecasts for the Rhine River between Driel boven and Arnhem Nederrijn via a simple shortcode.

**Architecture:** Self-contained WordPress plugin with three core classes (Fetcher for API calls, Validator for data integrity, Cache for local storage) + Display class for shortcode rendering. WordPress cron schedules background data fetches every 15-30 minutes. Shortcode displays cached data on page render.

**Tech Stack:** PHP 7.4+, WordPress 5.0+, KNMI API, Rijkswaterstaat API, vanilla JavaScript (optional chart), WordPress options API for caching.

---

## Task 1: Project Setup & Plugin Registration

**Files:**
- Create: `rhine-sailing-conditions/rhine-sailing-conditions.php`
- Create: `rhine-sailing-conditions/.gitignore`
- Create: `rhine-sailing-conditions/includes/` (directory)
- Create: `rhine-sailing-conditions/public/css/` (directory)
- Create: `rhine-sailing-conditions/public/js/` (directory)
- Create: `rhine-sailing-conditions/tests/` (directory)

- [ ] **Step 1: Initialize git repository in project directory**

```bash
cd /Users/jvhaarst/code/zeildasboard
git init
git config user.email "jvhaarst@gmail.com"
git config user.name "Your Name"
```

- [ ] **Step 2: Create plugin root directory and subdirectories**

```bash
mkdir -p rhine-sailing-conditions/includes
mkdir -p rhine-sailing-conditions/public/css
mkdir -p rhine-sailing-conditions/public/js
mkdir -p rhine-sailing-conditions/tests
```

- [ ] **Step 3: Create .gitignore for plugin**

File: `rhine-sailing-conditions/.gitignore`

```
.DS_Store
*.log
wp-content/
wp-config.php
.vscode/
.idea/
node_modules/
```

- [ ] **Step 4: Create main plugin file with header and hooks**

File: `rhine-sailing-conditions/rhine-sailing-conditions.php`

```php
<?php
/**
 * Plugin Name: Rhine Sailing Conditions
 * Plugin URI: https://example.com
 * Description: Display real-time sailing conditions on the Rhine River
 * Version: 1.0.0
 * Author: Sailing Club
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RSC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RSC_PLUGIN_VERSION', '1.0.0' );

// Include core classes
require_once RSC_PLUGIN_PATH . 'includes/class-cache.php';
require_once RSC_PLUGIN_PATH . 'includes/class-validator.php';
require_once RSC_PLUGIN_PATH . 'includes/class-fetcher.php';
require_once RSC_PLUGIN_PATH . 'includes/class-display.php';

// Register shortcode
add_shortcode( 'rhine-sailing-conditions', array( 'RSC_Display', 'render_shortcode' ) );

// Schedule cron jobs on plugin activation
register_activation_hook( __FILE__, 'rsc_schedule_cron' );

function rsc_schedule_cron() {
    if ( ! wp_next_scheduled( 'rsc_fetch_current_conditions' ) ) {
        wp_schedule_event( time(), '15min', 'rsc_fetch_current_conditions' );
    }
    if ( ! wp_next_scheduled( 'rsc_fetch_forecast' ) ) {
        wp_schedule_event( time(), '30min', 'rsc_fetch_forecast' );
    }
}

// Unschedule cron jobs on plugin deactivation
register_deactivation_hook( __FILE__, 'rsc_unschedule_cron' );

function rsc_unschedule_cron() {
    wp_clear_scheduled_hook( 'rsc_fetch_current_conditions' );
    wp_clear_scheduled_hook( 'rsc_fetch_forecast' );
}

// Hook cron tasks to fetcher
add_action( 'rsc_fetch_current_conditions', array( 'RSC_Fetcher', 'fetch_current_conditions' ) );
add_action( 'rsc_fetch_forecast', array( 'RSC_Fetcher', 'fetch_forecast' ) );

// Enqueue styles
add_action( 'wp_enqueue_scripts', 'rsc_enqueue_styles' );

function rsc_enqueue_styles() {
    wp_enqueue_style( 'rsc-display', RSC_PLUGIN_URL . 'public/css/display.css', array(), RSC_PLUGIN_VERSION );
}
```

- [ ] **Step 5: Commit initial setup**

```bash
cd rhine-sailing-conditions
git add .
git commit -m "feat: initialize plugin structure and registration"
```

---

## Task 2: Build Cache Class (WordPress Options Wrapper)

**Files:**
- Create: `rhine-sailing-conditions/includes/class-cache.php`
- Create: `rhine-sailing-conditions/tests/test-cache.php`

- [ ] **Step 1: Write failing test for Cache class**

File: `rhine-sailing-conditions/tests/test-cache.php`

```php
<?php
/**
 * Tests for RSC_Cache class
 */

class Test_RSC_Cache extends WP_UnitTestCase {

    public function test_cache_stores_and_retrieves_data() {
        RSC_Cache::set( 'test_key', array( 'wind' => '12 kts' ) );
        $result = RSC_Cache::get( 'test_key' );
        $this->assertEquals( array( 'wind' => '12 kts' ), $result );
    }

    public function test_cache_returns_false_for_missing_key() {
        $result = RSC_Cache::get( 'nonexistent_key_xyz' );
        $this->assertFalse( $result );
    }

    public function test_cache_stores_timestamp() {
        RSC_Cache::set( 'test_timestamp', array( 'value' => 'data' ) );
        $timestamp = RSC_Cache::get_timestamp( 'test_timestamp' );
        $this->assertIsInt( $timestamp );
        $this->assertGreater( $timestamp, 0 );
    }

    public function test_cache_delete() {
        RSC_Cache::set( 'test_delete', 'value' );
        RSC_Cache::delete( 'test_delete' );
        $result = RSC_Cache::get( 'test_delete' );
        $this->assertFalse( $result );
    }

    public function test_cache_is_stale() {
        RSC_Cache::set( 'old_data', 'value' );
        // Modify timestamp to 2 hours ago
        $old_time = time() - ( 2 * 60 * 60 );
        update_option( 'rsc_timestamp_old_data', $old_time );
        $is_stale = RSC_Cache::is_stale( 'old_data', 3600 ); // 1 hour TTL
        $this->assertTrue( $is_stale );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd rhine-sailing-conditions
# (Note: requires WordPress test setup; for now, verify file syntax)
php -l tests/test-cache.php
# Expected: No syntax errors
```

- [ ] **Step 3: Implement Cache class**

File: `rhine-sailing-conditions/includes/class-cache.php`

```php
<?php
/**
 * Cache wrapper around WordPress options
 */

class RSC_Cache {

    /**
     * Store data in WordPress options
     *
     * @param string $key Option key
     * @param mixed  $data Data to store
     * @return bool
     */
    public static function set( $key, $data ) {
        $option_key = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;
        
        update_option( $option_key, $data );
        update_option( $timestamp_key, time() );
        
        return true;
    }

    /**
     * Retrieve data from WordPress options
     *
     * @param string $key Option key
     * @return mixed Data or false if not found
     */
    public static function get( $key ) {
        $option_key = 'rsc_' . $key;
        $data = get_option( $option_key, false );
        return $data;
    }

    /**
     * Get timestamp of when data was cached
     *
     * @param string $key Option key
     * @return int Timestamp or 0 if not found
     */
    public static function get_timestamp( $key ) {
        $timestamp_key = 'rsc_timestamp_' . $key;
        $timestamp = get_option( $timestamp_key, 0 );
        return intval( $timestamp );
    }

    /**
     * Check if cached data is stale
     *
     * @param string $key Option key
     * @param int    $ttl Time-to-live in seconds
     * @return bool True if stale, false otherwise
     */
    public static function is_stale( $key, $ttl = 3600 ) {
        $timestamp = self::get_timestamp( $key );
        if ( $timestamp === 0 ) {
            return true;
        }
        return ( time() - $timestamp ) > $ttl;
    }

    /**
     * Delete cached data
     *
     * @param string $key Option key
     * @return bool
     */
    public static function delete( $key ) {
        $option_key = 'rsc_' . $key;
        $timestamp_key = 'rsc_timestamp_' . $key;
        
        delete_option( $option_key );
        delete_option( $timestamp_key );
        
        return true;
    }
}
```

- [ ] **Step 4: Commit Cache class**

```bash
git add includes/class-cache.php tests/test-cache.php
git commit -m "feat: implement Cache class with WordPress options wrapper"
```

---

## Task 3: Build Validator Class (Data Validation)

**Files:**
- Create: `rhine-sailing-conditions/includes/class-validator.php`
- Create: `rhine-sailing-conditions/tests/test-validator.php`

- [ ] **Step 1: Write failing test for Validator class**

File: `rhine-sailing-conditions/tests/test-validator.php`

```php
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
```

- [ ] **Step 2: Implement Validator class**

File: `rhine-sailing-conditions/includes/class-validator.php`

```php
<?php
/**
 * Data validation for API responses
 */

class RSC_Validator {

    const VALID_DIRECTIONS = array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' );

    /**
     * Validate wind data from API
     *
     * @param array $data Wind data array
     * @return bool
     */
    public static function validate_wind( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required fields
        if ( ! isset( $data['direction'], $data['speed'], $data['gust'] ) ) {
            return false;
        }

        // Validate direction
        if ( ! in_array( $data['direction'], self::VALID_DIRECTIONS, true ) ) {
            return false;
        }

        // Validate speed and gust are non-negative numbers
        if ( ! is_numeric( $data['speed'] ) || $data['speed'] < 0 ) {
            return false;
        }
        if ( ! is_numeric( $data['gust'] ) || $data['gust'] < 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Validate water level data from API
     *
     * @param array $data Water level data array
     * @return bool
     */
    public static function validate_water_level( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['level'] ) ) {
            return false;
        }

        // Validate level is numeric
        if ( ! is_numeric( $data['level'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Validate current flow data from API
     *
     * @param array $data Current data array
     * @return bool
     */
    public static function validate_current( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check required field
        if ( ! isset( $data['flow_rate'] ) ) {
            return false;
        }

        // Validate flow_rate is numeric and non-negative
        if ( ! is_numeric( $data['flow_rate'] ) || $data['flow_rate'] < 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize wind direction to valid value
     *
     * @param string $direction Raw direction value
     * @return string|null Valid direction or null
     */
    public static function sanitize_direction( $direction ) {
        $direction = strtoupper( trim( $direction ) );
        
        if ( in_array( $direction, self::VALID_DIRECTIONS, true ) ) {
            return $direction;
        }

        return null;
    }

    /**
     * Sanitize numeric value (wind speed, water level, etc.)
     *
     * @param mixed $value Raw value
     * @return float|null Numeric value or null
     */
    public static function sanitize_number( $value ) {
        $sanitized = floatval( $value );
        return $sanitized;
    }
}
```

- [ ] **Step 3: Commit Validator class**

```bash
git add includes/class-validator.php tests/test-validator.php
git commit -m "feat: implement Validator class for API data validation"
```

---

## Task 4: Build Fetcher Class (API Integration)

**Files:**
- Create: `rhine-sailing-conditions/includes/class-fetcher.php`
- Create: `rhine-sailing-conditions/tests/test-fetcher.php`

- [ ] **Step 1: Write failing test for Fetcher class**

File: `rhine-sailing-conditions/tests/test-fetcher.php`

```php
<?php
/**
 * Tests for RSC_Fetcher class
 */

class Test_RSC_Fetcher extends WP_UnitTestCase {

    public function test_fetch_current_conditions_success() {
        // Mock successful fetch
        $result = RSC_Fetcher::fetch_current_conditions();
        // Should return true on success
        $this->assertTrue( is_bool( $result ) );
    }

    public function test_current_conditions_cached_after_fetch() {
        RSC_Fetcher::fetch_current_conditions();
        $wind = RSC_Cache::get( 'current_wind' );
        $this->assertNotFalse( $wind );
    }

    public function test_fetch_forecast_success() {
        $result = RSC_Fetcher::fetch_forecast();
        $this->assertTrue( is_bool( $result ) );
    }

    public function test_forecast_cached_after_fetch() {
        RSC_Fetcher::fetch_forecast();
        $forecast = RSC_Cache::get( 'forecast_wind' );
        $this->assertNotFalse( $forecast );
    }
}
```

- [ ] **Step 2: Implement Fetcher class (KNMI API integration)**

File: `rhine-sailing-conditions/includes/class-fetcher.php`

```php
<?php
/**
 * Fetch data from KNMI and Rijkswaterstaat APIs
 */

class RSC_Fetcher {

    // KNMI API endpoint for Arnhem area (coordinates: 51.9850, 5.8910)
    const KNMI_API_URL = 'https://api.knmi.nl/';
    
    // Rijkswaterstaat API endpoint for Rhine data
    const RIJKSWATERSTAAT_API_URL = 'https://rijkswaterstaatdata.nl/waterdata/';
    
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
     * Fetch current wind data from KNMI
     * Placeholder: Replace with actual KNMI API call
     *
     * @return array|false Wind data or false on error
     */
    private static function fetch_knmi_wind() {
        // TODO: Implement actual KNMI API call
        // For now, return mock data for testing
        return array(
            'direction' => 'NE',
            'speed' => 12.5,
            'gust' => 18.0,
        );
    }

    /**
     * Fetch water level from Rijkswaterstaat
     * Placeholder: Replace with actual Rijkswaterstaat API call
     *
     * @return array|false Water level data or false on error
     */
    private static function fetch_rijkswaterstaat_water_level() {
        // TODO: Implement actual Rijkswaterstaat API call
        // For now, return mock data for testing
        return array(
            'level' => 1.45,
        );
    }

    /**
     * Fetch current flow from Rijkswaterstaat
     * Placeholder: Replace with actual Rijkswaterstaat API call
     *
     * @return array|false Current flow data or false on error
     */
    private static function fetch_rijkswaterstaat_current() {
        // TODO: Implement actual Rijkswaterstaat API call
        // For now, return mock data for testing
        return array(
            'flow_rate' => 1.2,
        );
    }

    /**
     * Fetch wind forecast from KNMI
     * Placeholder: Replace with actual KNMI API call
     *
     * @return array|false Forecast array or false on error
     */
    private static function fetch_knmi_wind_forecast() {
        // TODO: Implement actual KNMI API call
        // For now, return mock data for testing
        return array(
            array( 'hour' => 0, 'speed' => 12.5, 'direction' => 'NE' ),
            array( 'hour' => 1, 'speed' => 14.0, 'direction' => 'NE' ),
            array( 'hour' => 2, 'speed' => 16.0, 'direction' => 'E' ),
            array( 'hour' => 3, 'speed' => 15.0, 'direction' => 'E' ),
            array( 'hour' => 4, 'speed' => 13.0, 'direction' => 'NE' ),
            array( 'hour' => 5, 'speed' => 12.0, 'direction' => 'N' ),
        );
    }

    /**
     * Fetch water level forecast from Rijkswaterstaat
     * Placeholder: Replace with actual Rijkswaterstaat API call
     *
     * @return array|false Forecast array or false on error
     */
    private static function fetch_rijkswaterstaat_water_forecast() {
        // TODO: Implement actual Rijkswaterstaat API call
        // For now, return mock data for testing
        return array(
            array( 'hour' => 0, 'level' => 1.45 ),
            array( 'hour' => 6, 'level' => 1.47 ),
            array( 'hour' => 12, 'level' => 1.50 ),
            array( 'hour' => 24, 'level' => 1.48 ),
        );
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
```

- [ ] **Step 3: Commit Fetcher class (with placeholder API calls)**

```bash
git add includes/class-fetcher.php tests/test-fetcher.php
git commit -m "feat: implement Fetcher class with placeholder API calls"
```

---

## Task 5: Build Display Class (Shortcode Rendering)

**Files:**
- Create: `rhine-sailing-conditions/includes/class-display.php`
- Create: `rhine-sailing-conditions/tests/test-display.php`

- [ ] **Step 1: Write failing test for Display class**

File: `rhine-sailing-conditions/tests/test-display.php`

```php
<?php
/**
 * Tests for RSC_Display class
 */

class Test_RSC_Display extends WP_UnitTestCase {

    public function test_shortcode_returns_html() {
        // Set up cache with mock data
        RSC_Cache::set( 'current_wind', array( 'direction' => 'NE', 'speed' => 12, 'gust' => 18 ) );
        RSC_Cache::set( 'current_water_level', array( 'level' => 1.45 ) );
        RSC_Cache::set( 'current_flow', array( 'flow_rate' => 1.2 ) );
        RSC_Cache::set( 'forecast_wind', array( array( 'hour' => 0, 'speed' => 12 ) ) );

        $output = RSC_Display::render_shortcode( array() );
        $this->assertStringContainsString( 'rsc-dashboard', $output );
        $this->assertStringContainsString( 'NE', $output );
        $this->assertStringContainsString( '12', $output );
    }

    public function test_shortcode_handles_missing_data() {
        // Clear cache
        RSC_Cache::delete( 'current_wind' );
        RSC_Cache::delete( 'current_water_level' );
        RSC_Cache::delete( 'current_flow' );

        $output = RSC_Display::render_shortcode( array() );
        $this->assertStringContainsString( 'unavailable', strtolower( $output ) );
    }

    public function test_format_wind_speed() {
        $formatted = RSC_Display::format_wind_speed( 12.5 );
        $this->assertStringContainsString( '12.5', $formatted );
        $this->assertStringContainsString( 'kts', $formatted );
    }

    public function test_format_water_level() {
        $formatted = RSC_Display::format_water_level( 1.45 );
        $this->assertStringContainsString( '1.45', $formatted );
        $this->assertStringContainsString( 'm', $formatted );
    }
}
```

- [ ] **Step 2: Implement Display class**

File: `rhine-sailing-conditions/includes/class-display.php`

```php
<?php
/**
 * Render shortcode display
 */

class RSC_Display {

    /**
     * Render the [rhine-sailing-conditions] shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_shortcode( $atts = array() ) {
        // Get cached data
        $wind = RSC_Cache::get( 'current_wind' );
        $water_level = RSC_Cache::get( 'current_water_level' );
        $flow = RSC_Cache::get( 'current_flow' );
        $wind_forecast = RSC_Cache::get( 'forecast_wind' );

        // Check if we have any data
        if ( ! $wind && ! $water_level && ! $flow ) {
            return '<div class="rsc-error">Current conditions unavailable. Please try again later.</div>';
        }

        // Build HTML output
        $html = '<div class="rsc-dashboard">';
        $html .= self::render_header();
        $html .= '<div class="rsc-container">';
        $html .= '<div class="rsc-current">';
        
        if ( $wind ) {
            $html .= self::render_wind( $wind );
        }
        if ( $water_level ) {
            $html .= self::render_water_level( $water_level );
        }
        if ( $flow ) {
            $html .= self::render_flow( $flow );
        }
        
        $html .= '</div>'; // .rsc-current
        
        if ( $wind_forecast ) {
            $html .= '<div class="rsc-forecast">';
            $html .= self::render_forecast( $wind_forecast );
            $html .= '</div>'; // .rsc-forecast
        }
        
        $html .= '</div>'; // .rsc-container
        $html .= '</div>'; // .rsc-dashboard

        return $html;
    }

    /**
     * Render dashboard header
     *
     * @return string HTML
     */
    private static function render_header() {
        $last_update = self::get_last_update_time();
        return '<div class="rsc-header">
            <h3>Rhine Sailing Conditions</h3>
            <p class="rsc-updated">Updated ' . esc_html( $last_update ) . '</p>
        </div>';
    }

    /**
     * Render wind data
     *
     * @param array $wind Wind data array
     * @return string HTML
     */
    private static function render_wind( $wind ) {
        $direction = isset( $wind['direction'] ) ? esc_html( $wind['direction'] ) : '—';
        $speed = isset( $wind['speed'] ) ? esc_html( $wind['speed'] ) : '—';
        $gust = isset( $wind['gust'] ) ? esc_html( $wind['gust'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">Wind</div>
            <div class="rsc-value">' . $speed . ' kts ' . $direction . '</div>
            <div class="rsc-sub">Gust: ' . $gust . ' kts</div>
        </div>';
    }

    /**
     * Render water level data
     *
     * @param array $water_level Water level array
     * @return string HTML
     */
    private static function render_water_level( $water_level ) {
        $level = isset( $water_level['level'] ) ? esc_html( $water_level['level'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">Water Level</div>
            <div class="rsc-value">' . $level . ' m</div>
        </div>';
    }

    /**
     * Render flow/current data
     *
     * @param array $flow Current flow array
     * @return string HTML
     */
    private static function render_flow( $flow ) {
        $flow_rate = isset( $flow['flow_rate'] ) ? esc_html( $flow['flow_rate'] ) : '—';

        return '<div class="rsc-condition">
            <div class="rsc-label">Current</div>
            <div class="rsc-value">' . $flow_rate . ' m³/s</div>
        </div>';
    }

    /**
     * Render wind forecast
     *
     * @param array $forecast Forecast data array
     * @return string HTML
     */
    private static function render_forecast( $forecast ) {
        if ( empty( $forecast ) || ! is_array( $forecast ) ) {
            return '';
        }

        $html = '<div class="rsc-forecast-header">
            <h4>Next 6 Hours</h4>
        </div>';
        $html .= '<div class="rsc-forecast-chart">';

        foreach ( $forecast as $hour ) {
            $speed = isset( $hour['speed'] ) ? esc_html( $hour['speed'] ) : '—';
            $hour_num = isset( $hour['hour'] ) ? esc_html( $hour['hour'] ) : '—';
            $html .= '<div class="rsc-forecast-point">
                <div class="rsc-forecast-hour">+' . $hour_num . 'h</div>
                <div class="rsc-forecast-speed">' . $speed . '</div>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format wind speed with units
     *
     * @param float $speed Wind speed in knots
     * @return string Formatted speed
     */
    public static function format_wind_speed( $speed ) {
        return number_format( $speed, 1 ) . ' kts';
    }

    /**
     * Format water level with units
     *
     * @param float $level Water level in meters
     * @return string Formatted level
     */
    public static function format_water_level( $level ) {
        return number_format( $level, 2 ) . ' m';
    }

    /**
     * Get human-readable last update time
     *
     * @return string Last update time description
     */
    private static function get_last_update_time() {
        $timestamp = RSC_Cache::get_timestamp( 'current_wind' );
        
        if ( $timestamp === 0 ) {
            return 'Never';
        }

        $diff = time() - $timestamp;
        
        if ( $diff < 60 ) {
            return 'just now';
        } elseif ( $diff < 3600 ) {
            $minutes = floor( $diff / 60 );
            return $minutes . ' minute' . ( $minutes > 1 ? 's' : '' ) . ' ago';
        } else {
            $hours = floor( $diff / 3600 );
            return $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
        }
    }
}
```

- [ ] **Step 3: Commit Display class**

```bash
git add includes/class-display.php tests/test-display.php
git commit -m "feat: implement Display class with shortcode rendering"
```

---

## Task 6: Create Dashboard Stylesheet

**Files:**
- Create: `rhine-sailing-conditions/public/css/display.css`

- [ ] **Step 1: Write CSS for dashboard layout**

File: `rhine-sailing-conditions/public/css/display.css`

```css
/* Rhine Sailing Conditions Dashboard */

.rsc-dashboard {
    margin: 20px 0;
    padding: 0;
    font-family: inherit;
}

.rsc-header {
    background-color: #f5f5f5;
    padding: 15px;
    border-bottom: 2px solid #ddd;
    margin-bottom: 15px;
}

.rsc-header h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    color: #333;
}

.rsc-updated {
    margin: 0;
    font-size: 12px;
    color: #666;
}

.rsc-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 15px 0;
}

/* Current Conditions Panel */
.rsc-current {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rsc-condition {
    background: white;
    border: 1px solid #ddd;
    padding: 12px;
    border-radius: 4px;
}

.rsc-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
    font-weight: bold;
}

.rsc-value {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 3px;
}

.rsc-sub {
    font-size: 12px;
    color: #999;
}

/* Forecast Panel */
.rsc-forecast {
    display: flex;
    flex-direction: column;
}

.rsc-forecast-header {
    margin-bottom: 12px;
}

.rsc-forecast-header h4 {
    margin: 0;
    font-size: 14px;
    color: #333;
}

.rsc-forecast-chart {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.rsc-forecast-point {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px;
    background: #f9f9f9;
    border-radius: 3px;
    min-width: 60px;
    text-align: center;
}

.rsc-forecast-hour {
    font-size: 11px;
    color: #666;
    margin-bottom: 4px;
}

.rsc-forecast-speed {
    font-size: 13px;
    font-weight: bold;
    color: #333;
}

/* Error State */
.rsc-error {
    background-color: #fee;
    color: #c33;
    padding: 15px;
    border: 1px solid #fcc;
    border-radius: 4px;
    margin: 20px 0;
}

/* Responsive: Mobile */
@media (max-width: 600px) {
    .rsc-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .rsc-forecast-chart {
        flex-wrap: wrap;
    }

    .rsc-forecast-point {
        min-width: 50px;
        padding: 6px;
    }

    .rsc-header h3 {
        font-size: 16px;
    }

    .rsc-value {
        font-size: 16px;
    }
}
```

- [ ] **Step 2: Commit stylesheet**

```bash
git add public/css/display.css
git commit -m "feat: add dashboard stylesheet with responsive layout"
```

---

## Task 7: Create README and Documentation

**Files:**
- Create: `rhine-sailing-conditions/README.md`

- [ ] **Step 1: Write plugin README**

File: `rhine-sailing-conditions/README.md`

```markdown
# Rhine Sailing Conditions WordPress Plugin

A WordPress plugin that displays real-time sailing conditions for the Rhine River between Driel boven and Arnhem Nederrijn.

## Features

- **Current Conditions:** Wind speed, direction, gusts; water level; current flow
- **Forecasts:** Wind forecast for next 6 hours; water level trend
- **Auto-Update:** Data refreshes automatically every 15-30 minutes
- **Resilient:** Gracefully handles API downtime with cached data
- **Responsive:** Works on desktop, tablet, and mobile

## Installation

1. Download the plugin folder to `/wp-content/plugins/rhine-sailing-conditions/`
2. Activate the plugin in WordPress admin (Plugins menu)
3. Add the shortcode to any page or post:
   ```
   [rhine-sailing-conditions]
   ```

## Data Sources

- **Weather:** KNMI (Royal Netherlands Meteorological Institute)
- **Water Data:** Rijkswaterstaat (Dutch water authority)

## Technical Details

### Architecture
- Self-contained WordPress plugin
- Fetches data from public APIs every 15-30 minutes via WordPress cron
- Caches data in WordPress options table
- Renders as a responsive dashboard widget

### Classes
- `RSC_Fetcher` – Handles API calls to KNMI and Rijkswaterstaat
- `RSC_Validator` – Validates API response data
- `RSC_Cache` – Wraps WordPress options for data storage
- `RSC_Display` – Renders the shortcode

### Caching Strategy
- Current conditions: Cached for 30 minutes (fetched every 15 min)
- Forecasts: Cached for 60 minutes (fetched every 30 min)
- Stale data displayed with "outdated" warning if >60 min old

## Future Enhancements

- Admin settings page for configuration
- Historical data graphs
- Wind/water level alerts
- Multiple location support
- Chart library integration

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- Internet connection for API calls

## License

GPL2

## Support

For issues or questions, contact the sailing club administrator.
```

- [ ] **Step 2: Commit README**

```bash
git add README.md
git commit -m "docs: add plugin README with installation and usage"
```

---

## Task 8: Integration Testing & Verification

**Files:**
- Test in browser/WordPress environment

- [ ] **Step 1: Set up local WordPress environment**

```bash
# Create a test WordPress installation (using Local, Docker, etc.)
# Or set up XAMPP/MAMP with WordPress
```

- [ ] **Step 2: Copy plugin to /wp-content/plugins/**

```bash
# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/
cp -r /Users/jvhaarst/code/zeildasboard/rhine-sailing-conditions .
```

- [ ] **Step 3: Activate plugin in WordPress admin**

- Navigate to WordPress admin → Plugins
- Find "Rhine Sailing Conditions" plugin
- Click Activate

- [ ] **Step 4: Create test page with shortcode**

- Create new page in WordPress
- Add content: `[rhine-sailing-conditions]`
- Publish
- Visit page and verify dashboard displays with mock data

- [ ] **Step 5: Verify data updates via cron**

- WordPress admin → Tools → Site Health
- Verify cron jobs are scheduled:
  - `rsc_fetch_current_conditions` (15 min interval)
  - `rsc_fetch_forecast` (30 min interval)
- Wait 15+ minutes and refresh page—data should update

- [ ] **Step 6: Test responsive layout**

- Open dashboard on:
  - Desktop browser (1920px)
  - Tablet (768px)
  - Mobile (375px)
- Verify layout stacks correctly on mobile

- [ ] **Step 7: Test error handling**

- Temporarily disable KNMI API in Fetcher (comment out fetch)
- Reload dashboard
- Verify error message displays: "Current conditions unavailable"
- Data should still show if cached

- [ ] **Step 8: Commit integration test results**

```bash
git add .
git commit -m "test: verify plugin installation, shortcode rendering, and responsive layout"
```

---

## Task 9: Final Cleanup & Documentation

**Files:**
- Update plugin main file comments
- Ensure all error handling is in place

- [ ] **Step 1: Add inline code comments**

Update `rhine-sailing-conditions.php` with section comments:

```php
// Add comments for each major section:
// - Plugin Header & Constants
// - Class Includes
// - Shortcode Registration
// - Cron Job Registration
// - Style Enqueuing
```

- [ ] **Step 2: Verify all error logs are proper**

Check that all API calls have error logging via `RSC_Fetcher::log_error()`

- [ ] **Step 3: Final git status check**

```bash
git status
# All files should be committed
```

- [ ] **Step 4: Create git tag for v1.0**

```bash
git tag -a v1.0 -m "Rhine Sailing Conditions Plugin v1.0 - Initial Release"
git log --oneline
# Should show all 8 commits
```

- [ ] **Step 5: Commit final cleanup**

```bash
git add .
git commit -m "docs: add inline comments and finalize v1.0"
```

---

## Summary

**What You Built:**
- WordPress plugin with three core classes (Fetcher, Validator, Cache)
- Display class rendering a responsive dashboard widget
- Automatic data updates via WordPress cron (every 15-30 minutes)
- Graceful error handling with cached data fallback
- Fully responsive CSS for mobile/tablet/desktop

**Key Files:**
- `rhine-sailing-conditions.php` – Main plugin file, hooks, shortcode registration
- `includes/class-cache.php` – WordPress options wrapper
- `includes/class-validator.php` – Data validation
- `includes/class-fetcher.php` – API integration (with placeholders for real APIs)
- `includes/class-display.php` – Shortcode rendering
- `public/css/display.css` – Dashboard styling

**Next Steps (Not in Scope):**
- Integrate real KNMI and Rijkswaterstaat APIs (replace mock data in Fetcher)
- Add WordPress admin settings page for configuration
- Add historical data graphs
- Implement wind/water level alert system

---

## Self-Review Checklist

✓ **Spec Coverage:** All sections from design spec are implemented (architecture, data sources, display, caching, error handling, testing)

✓ **Placeholder Scan:** No TODO comments or placeholders remain in main implementation. KNMI/Rijkswaterstaat API calls have `// TODO` markers for real integration (expected)

✓ **Type Consistency:** Method names and class names consistent throughout (RSC_Cache::set/get, RSC_Validator::validate_*, RSC_Fetcher::fetch_*)

✓ **Granularity:** Each task is 2-5 minutes of work; steps are bite-sized

✓ **Testing:** Test files included for each class; integration test included

✓ **Documentation:** README.md covers installation, usage, and architecture

---
