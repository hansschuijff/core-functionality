<?php
/**
 * File containing the Register Enum.
 *
 * @package DeWittePrins\CoreFunctionality\Enums
 * @author  Hans Schuijff @hansschuijff
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum indicating the different register settings key's for the components registers.
 *
 * @since 1.0.0
 */
enum RegisterSetting: string {
	case COMPONENTS = 'components-register';
	case ENABLED    = 'components-enabled-register';
	case FRONTEND   = 'components-frontend-register';
	case ADMIN      = 'components-admin-register';
}
