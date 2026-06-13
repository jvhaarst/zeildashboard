<?php
/**
 * Shared data layer for the standalone example dashboards.
 *
 * Makes the dashboards production-safe: every upstream call goes through cURL
 * (works where allow_url_fopen is disabled), responses are cached on disk so
 * concurrent page loads don't hammer Open-Meteo / Rijkswaterstaat, and a failed
 * fetch falls back to the last good cached value instead of blanking the page.
 *
 * The qualitative sailing assessment is delegated to the plugin's framework-
 * agnostic RSC_Assessment class, so its thresholds are defined in exactly one
 * place and never drift between the plugin and these dashboards.
 */

// Dutch timestamps regardless of the server's default timezone.
date_default_timezone_set( 'Europe/Amsterdam' );

require_once __DIR__ . '/../../includes/class-assessment.php';

// km/h -> knots and m/s -> knots conversion factors.
const RSC_KMH_TO_KNOTS = 0.539957;
const RSC_MPS_TO_KNOTS = 1.94384;

// Cache lifetimes (seconds). Sources update ~every 10-30 min upstream.
const RSC_CACHE_TTL_CURRENT  = 900;  // 15 min
const RSC_CACHE_TTL_FORECAST = 1800; // 30 min

/**
 * Directory used for the on-disk response cache (created on demand).
 *
 * @return string Absolute path to the cache directory.
 */
function rsc_cache_dir() {
    $dir = sys_get_temp_dir() . '/rsc-example-cache';
    if ( ! is_dir( $dir ) ) {
        @mkdir( $dir, 0700, true );
    }
    return $dir;
}

/**
 * Read a cached value if it is still fresh.
 *
 * @param string $key Cache key.
 * @param int    $ttl Maximum age in seconds (use PHP_INT_MAX to accept stale).
 * @return mixed|null Decoded data, or null when missing/expired.
 */
function rsc_cache_get( $key, $ttl ) {
    $file = rsc_cache_dir() . '/' . preg_replace( '/[^a-z0-9_]/i', '_', $key ) . '.json';
    if ( ! is_readable( $file ) ) {
        return null;
    }
    $raw = json_decode( (string) file_get_contents( $file ), true );
    if ( ! is_array( $raw ) || ! isset( $raw['ts'], $raw['data'] ) ) {
        return null;
    }
    if ( ( time() - (int) $raw['ts'] ) > $ttl ) {
        return null;
    }
    return $raw['data'];
}

/**
 * Write a value to the on-disk cache (atomic rename).
 *
 * @param string $key  Cache key.
 * @param mixed  $data Data to store.
 * @return void
 */
function rsc_cache_set( $key, $data ) {
    $file = rsc_cache_dir() . '/' . preg_replace( '/[^a-z0-9_]/i', '_', $key ) . '.json';
    $tmp  = $file . '.' . getmypid() . '.tmp';
    if ( false !== file_put_contents( $tmp, json_encode( array( 'ts' => time(), 'data' => $data ) ) ) ) {
        @rename( $tmp, $file );
    }
}

/**
 * Perform an HTTP request and decode the JSON body.
 *
 * Uses cURL when available, otherwise a stream-context fallback. Returns null
 * on any transport error or non-200 status.
 *
 * @param string      $url     Request URL.
 * @param string      $method  'GET' or 'POST'.
 * @param string|null $body    Request body (for POST).
 * @param int         $timeout Timeout in seconds.
 * @return array|null Decoded JSON array, or null on error.
 */
function rsc_http_json( $url, $method = 'GET', $body = null, $timeout = 10 ) {
    $user_agent = 'Rhine-Sailing-Dashboard/1.0';

    if ( function_exists( 'curl_init' ) ) {
        $ch = curl_init( $url );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => $user_agent,
            CURLOPT_FOLLOWLOCATION => true,
        ) );
        if ( 'POST' === $method ) {
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
        }
        $response = curl_exec( $ch );
        $status   = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
        // curl_close() is a deprecated no-op on PHP 8.0+; only needed on 7.x.
        if ( PHP_VERSION_ID < 80000 ) {
            curl_close( $ch );
        }
        if ( false === $response || 200 !== $status ) {
            return null;
        }
    } else {
        $opts = array( 'http' => array(
            'method'        => $method,
            'timeout'       => $timeout,
            'header'        => 'User-Agent: ' . $user_agent . "\r\n" . ( 'POST' === $method ? "Content-Type: application/json\r\n" : '' ),
            'ignore_errors' => true,
        ) );
        if ( null !== $body ) {
            $opts['http']['content'] = $body;
        }
        $response = @file_get_contents( $url, false, stream_context_create( $opts ) );
        if ( false === $response ) {
            return null;
        }
    }

    $data = json_decode( $response, true );
    return is_array( $data ) ? $data : null;
}

/**
 * Fetch JSON with a read-through disk cache. On a failed fetch, serves the last
 * good cached value (any age) so a transient API outage never blanks the page.
 *
 * @param string      $url    Request URL.
 * @param string      $key    Cache key.
 * @param int         $ttl    Fresh-cache lifetime in seconds.
 * @param string      $method 'GET' or 'POST'.
 * @param string|null $body   Request body (for POST).
 * @return array|null Decoded data, or null when no data is available at all.
 */
function rsc_fetch_json( $url, $key, $ttl, $method = 'GET', $body = null ) {
    $fresh = rsc_cache_get( $key, $ttl );
    if ( null !== $fresh ) {
        return $fresh;
    }
    $data = rsc_http_json( $url, $method, $body );
    if ( null !== $data ) {
        rsc_cache_set( $key, $data );
        return $data;
    }
    // Fetch failed — fall back to the last good value regardless of age.
    return rsc_cache_get( $key, PHP_INT_MAX );
}

/**
 * Convert degrees to a 16-point Dutch cardinal direction.
 *
 * @param int $degrees Wind direction in degrees (0-360).
 * @return string Cardinal direction (N, NNO, NO, ...).
 */
function rsc_degrees_to_direction( $degrees ) {
    $directions = array( 'N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW' );
    $index      = (int) round( $degrees / 22.5 ) % 16;
    return $directions[ $index ];
}

/**
 * Convert a sustained wind speed in knots to the Beaufort scale (0-12).
 *
 * @param float $knots Sustained wind speed in knots.
 * @return int Beaufort force.
 */
function rsc_knots_to_beaufort( $knots ) {
    $lower_bounds = array( 1, 4, 7, 11, 17, 22, 28, 34, 41, 48, 56, 64 );
    $force        = 0;
    foreach ( $lower_bounds as $index => $min_knots ) {
        if ( $knots >= $min_knots ) {
            $force = $index + 1;
        }
    }
    return $force;
}

/**
 * Qualitative sailing assessment — delegated to the shared RSC_Assessment so
 * the thresholds match the WordPress plugin exactly.
 *
 * @param float $wind_knots    Sustained wind speed in knots.
 * @param float $current_knots Water current speed in knots.
 * @return array See RSC_Assessment::evaluate().
 */
function rsc_get_sailing_conditions( $wind_knots, $current_knots ) {
    return RSC_Assessment::evaluate( $wind_knots, $current_knots );
}
