<?php
/**
 * Plugin Name: Rhine Sailing Conditions
 * Plugin URI: https://example.com
 * Description: Display real-time sailing conditions on the Rhine River
 * Version: 1.3.0
 * Author: Sailing Club
 * License: GPL2
 * Text Domain: rhine-sailing-conditions
 * Domain Path: /languages
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
define( 'RSC_PLUGIN_VERSION', '1.3.0' );

// ============================================================================
// Class Includes
// ============================================================================
// Load core plugin classes for caching, validation, data fetching, and display.
require_once RSC_PLUGIN_PATH . 'includes/class-cache.php';
require_once RSC_PLUGIN_PATH . 'includes/class-validator.php';
require_once RSC_PLUGIN_PATH . 'includes/class-fetcher.php';
require_once RSC_PLUGIN_PATH . 'includes/class-display.php';

// ============================================================================
// Translations
// ============================================================================
// Source strings are English; shipped translations live in /languages.
// The plugin defaults its own UI to Dutch (nl_NL) regardless of the site
// locale. To use another language (e.g. Frisian) drop in a matching
// .mo file and override the locale via the 'rsc_locale' filter:
//
//     add_filter( 'rsc_locale', function () { return 'fy_NL'; } );
//
add_filter( 'plugin_locale', 'rsc_plugin_locale', 10, 2 );

function rsc_plugin_locale( $locale, $domain ) {
    if ( 'rhine-sailing-conditions' === $domain ) {
        return apply_filters( 'rsc_locale', 'nl_NL' );
    }
    return $locale;
}

add_action( 'init', 'rsc_load_textdomain' );

function rsc_load_textdomain() {
    load_plugin_textdomain( 'rhine-sailing-conditions', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// ============================================================================
// Shortcode Registration
// ============================================================================
// Register the [rhine-sailing-conditions] shortcode for displaying data in posts/pages.
add_shortcode( 'rhine-sailing-conditions', array( 'RSC_Display', 'render_shortcode' ) );

// ============================================================================
// Custom Cron Schedules
// ============================================================================
// WordPress ships only hourly/twicedaily/daily intervals. Register the
// 15-minute and 30-minute intervals the plugin schedules events on, otherwise
// wp_schedule_event() silently fails and no data is ever fetched.
add_filter( 'cron_schedules', 'rsc_add_cron_schedules' );

function rsc_add_cron_schedules( $schedules ) {
    $schedules['15min'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 15 minutes', 'rhine-sailing-conditions' ),
    );
    $schedules['30min'] = array(
        'interval' => 30 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 30 minutes', 'rhine-sailing-conditions' ),
    );
    return $schedules;
}

// ============================================================================
// Activation/Deactivation Hooks
// ============================================================================
// Schedule/unschedule cron tasks when plugin is activated/deactivated.

register_activation_hook( __FILE__, 'rsc_schedule_cron' );

function rsc_schedule_cron() {
    // Ensure the custom intervals are registered before scheduling against them.
    add_filter( 'cron_schedules', 'rsc_add_cron_schedules' );

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
