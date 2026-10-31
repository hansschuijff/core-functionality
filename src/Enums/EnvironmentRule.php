<?php
/**
 * File containing the EnvironmentRule Enum.
 *
 * @package DeWittePrins\CoreFunctionality\Enums
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function __;

/**
 * Enum of what happens with a module or feature on one kind of environment.
 *
 * Next to its status (how far the code is ready), a component has a rule per environment, based on
 * what it does: an e-mail blocker belongs on development, never on production. "Automatically on"
 * and "automatically off" leave the site no choice; in between, the administrator decides on the
 * Feature Activation tab (Hans, October 2026).
 *
 * @since 1.0.0
 */
enum EnvironmentRule: string {

	case AUTO_ON    = 'auto-on';
	case ADJUSTABLE = 'adjustable';
	case AUTO_OFF   = 'auto-off';

	/**
	 * Returns the translated label of the rule.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	public function get_label(): string {
		return match ( $this ) {
			self::AUTO_ON    => __( 'Automatically on', 'core-functionality' ),
			self::ADJUSTABLE => __( 'Freely adjustable', 'core-functionality' ),
			self::AUTO_OFF   => __( 'Automatically off', 'core-functionality' ),
		};
	}
}
