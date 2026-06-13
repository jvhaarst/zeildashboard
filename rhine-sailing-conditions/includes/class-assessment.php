<?php
/**
 * Qualitative sailing-conditions assessment.
 *
 * Pure logic with no WordPress dependency: every label returned is an English
 * source string. The display layer wraps these in __() for translation. Keeping
 * this framework-agnostic makes it unit-testable on its own and lets the
 * standalone example dashboards share the same thresholds.
 *
 * @package RhineSailingConditions
 * @since 1.4.0
 */

class RSC_Assessment {

	/**
	 * Evaluate wind and current into qualitative levels and a recommendation.
	 *
	 * @param float $wind_knots    Sustained wind speed in knots.
	 * @param float $current_knots Water current speed in knots.
	 * @return array {
	 *     @type string $status         'good' or 'caution' (drives badge colour).
	 *     @type string $recommendation English recommendation source string.
	 *     @type array  $wind           [ 'level' => string, 'description' => string ].
	 *     @type array  $water          [ 'level' => string, 'description' => string ].
	 * }
	 */
	public static function evaluate( $wind_knots, $current_knots ) {
		$result = array();

		// Wind level.
		if ( $wind_knots < 3 ) {
			$result['wind'] = array( 'level' => 'Calm', 'description' => 'No wind' );
		} elseif ( $wind_knots < 6 ) {
			$result['wind'] = array( 'level' => 'Light', 'description' => 'Light breeze' );
		} elseif ( $wind_knots < 10 ) {
			$result['wind'] = array( 'level' => 'Moderate', 'description' => 'Nice breeze' );
		} elseif ( $wind_knots < 15 ) {
			$result['wind'] = array( 'level' => 'Strong', 'description' => 'Strong wind' );
		} else {
			$result['wind'] = array( 'level' => 'Very strong', 'description' => 'Dangerous conditions' );
		}

		// Current level.
		if ( $current_knots < 0.5 ) {
			$result['water'] = array( 'level' => 'Weak', 'description' => 'Very weak current' );
		} elseif ( $current_knots < 1.0 ) {
			$result['water'] = array( 'level' => 'Light', 'description' => 'Light current' );
		} elseif ( $current_knots < 2.0 ) {
			$result['water'] = array( 'level' => 'Moderate', 'description' => 'Average current' );
		} elseif ( $current_knots < 3.0 ) {
			$result['water'] = array( 'level' => 'Strong', 'description' => 'Strong current' );
		} else {
			$result['water'] = array( 'level' => 'Very strong', 'description' => 'Very strong current' );
		}

		// Overall recommendation. 'status' is language-independent; 'recommendation'
		// is an English source string translated downstream.
		if ( $wind_knots >= 6 && $wind_knots < 15 && $current_knots < 2.5 ) {
			$result['status']         = 'good';
			$result['recommendation'] = 'Good conditions for sailing';
		} elseif ( $wind_knots < 6 ) {
			$result['status']         = 'caution';
			$result['recommendation'] = 'Insufficient wind for good sailing';
		} elseif ( $wind_knots >= 15 ) {
			$result['status']         = 'caution';
			$result['recommendation'] = 'Wind too strong - caution advised';
		} elseif ( $current_knots >= 2.5 ) {
			$result['status']         = 'caution';
			$result['recommendation'] = 'Current too strong - caution advised';
		} else {
			$result['status']         = 'caution';
			$result['recommendation'] = 'Check conditions before sailing';
		}

		return $result;
	}
}
