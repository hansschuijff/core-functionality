<?php
/**
 * Admin Toolbar Core Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality
 * @subpackage Modules\ActiveWoo
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\ActiveWoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class ActiveWoo
 *
 * @since 1.0.0
 */
class ActiveWoo extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'active-woo',
			name:          __( 'ActiveWoo', 'core-functionality' ),
			description:   __( 'Customizations and adaptations for the ActiveWoo plugin.', 'core-functionality' ),
		);
	}
}
