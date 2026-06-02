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
