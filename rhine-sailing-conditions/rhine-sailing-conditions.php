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

// ============================================================================
// Plugin Header & Constants
// ============================================================================
// Define plugin paths, URLs, and version constants for use throughout the plugin.
define( 'RSC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RSC_PLUGIN_VERSION', '1.0.0' );

// ============================================================================
// Class Includes
// ============================================================================
// Load core plugin classes for caching, validation, data fetching, and display.
require_once RSC_PLUGIN_PATH . 'includes/class-cache.php';
require_once RSC_PLUGIN_PATH . 'includes/class-validator.php';
require_once RSC_PLUGIN_PATH . 'includes/class-fetcher.php';
require_once RSC_PLUGIN_PATH . 'includes/class-display.php';

// ============================================================================
// Shortcode Registration
// ============================================================================
// Register the [rhine-sailing-conditions] shortcode for displaying data in posts/pages.
add_shortcode( 'rhine-sailing-conditions', array( 'RSC_Display', 'render_shortcode' ) );

// ============================================================================
// Activation/Deactivation Hooks
// ============================================================================
// Schedule/unschedule cron tasks when plugin is activated/deactivated.

register_activation_hook( __FILE__, 'rsc_schedule_cron' );

function rsc_schedule_cron() {
    if ( ! wp_next_scheduled( 'rsc_fetch_current_conditions' ) ) {
        wp_schedule_event( time(), '15min', 'rsc_fetch_current_conditions' );
    }
    if ( ! wp_next_scheduled( 'rsc_fetch_forecast' ) ) {
        wp_schedule_event( time(), '30min', 'rsc_fetch_forecast' );
    }
}

register_deactivation_hook( __FILE__, 'rsc_unschedule_cron' );

function rsc_unschedule_cron() {
    wp_clear_scheduled_hook( 'rsc_fetch_current_conditions' );
    wp_clear_scheduled_hook( 'rsc_fetch_forecast' );
}

// ============================================================================
// Cron Action Hooks
// ============================================================================
// Connect cron events to their handler methods in the Fetcher class.
add_action( 'rsc_fetch_current_conditions', array( 'RSC_Fetcher', 'fetch_current_conditions' ) );
add_action( 'rsc_fetch_forecast', array( 'RSC_Fetcher', 'fetch_forecast' ) );

// ============================================================================
// Style Enqueuing
// ============================================================================
// Load plugin stylesheet on the frontend.
add_action( 'wp_enqueue_scripts', 'rsc_enqueue_styles' );

function rsc_enqueue_styles() {
    wp_enqueue_style( 'rsc-display', RSC_PLUGIN_URL . 'public/css/display.css', array(), RSC_PLUGIN_VERSION );
}
