<?php
/**
 * Admin Toolbar Core Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality
 * @subpackage Modules\Cleaner
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Cleaner;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class Cleaner
 *
 * @since 1.0.0
 */
class Cleaner extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'cf-cleaner-module',
			name:          __( 'WordPress Cleaner', 'core-functionality' ),
			description:   __( 'Utilities that keep WordPress performing efficiently.', 'core-functionality' ),
		);
	}
}
