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
 * Enum indicating the different register settings filenames (without path)
 * for the components registers.
 *
 * @since 1.0.0
 */
enum RegisterFile: string {
	case COMPONENTS = 'components-register.php';
	case ENABLED    = 'components-enabled-register.php';
	case FRONTEND   = 'components-frontend-register.php';
	case ADMIN      = 'components-admin-register.php';
}
