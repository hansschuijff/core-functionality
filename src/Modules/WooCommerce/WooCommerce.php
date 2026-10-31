<?php
/**
 * WooCommerce Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class WooCommerce
 *
 * Additions to WooCommerce. Its features only run when WooCommerce is ready.
 *
 * @since 1.0.0
 */
class WooCommerce extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'woocommerce',
			name:         __( 'WooCommerce', 'core-functionality' ),
			description:  __( 'Additions to WooCommerce.', 'core-functionality' ),
			dependencies: 'woocommerce',
		);
	}
}
