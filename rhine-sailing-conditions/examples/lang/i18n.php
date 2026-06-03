<?php
/**
 * Minimal translation-table loader for the standalone example dashboards.
 *
 * These examples run outside WordPress (php -S), so they cannot use the
 * plugin's gettext setup. Instead each language is a flat PHP array mapping
 * the English source string to its translation (see nl.php, fy.php).
 *
 * Default UI language is Dutch ('nl'). To switch, set the RSC_LANG environment
 * variable before serving, e.g.:
 *
 *     RSC_LANG=fy php -S localhost:8765
 *
 * Adding a language = drop a new <code>.php</code> table in this folder.
 */

$rsc_lang         = preg_replace( '/[^a-z_]/', '', strtolower( getenv( 'RSC_LANG' ) ?: 'nl' ) );
$rsc_table_file   = __DIR__ . '/' . $rsc_lang . '.php';
$rsc_translations = is_readable( $rsc_table_file ) ? include $rsc_table_file : array();
if ( ! is_array( $rsc_translations ) ) {
    $rsc_translations = array();
}

/**
 * Translate an English source string for the current example language.
 * Falls back to the English string when no translation exists.
 *
 * @param string $key English source string.
 * @return string Translated string (raw; caller escapes if needed).
 */
function t( $key ) {
    global $rsc_translations;
    return isset( $rsc_translations[ $key ] ) ? $rsc_translations[ $key ] : $key;
}
