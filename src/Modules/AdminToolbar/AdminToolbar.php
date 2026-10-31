<?php
/**
 * Admin Toolbar Core Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality\Modules\AdminToolbar
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AdminToolbar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;
/**
 * Class AdminToolbar
 *
 * @since 1.0.0
 */
class AdminToolbar extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'admin_toolbar',
			name:          __( 'Admin Toolbar', 'core-functionality' ),
			description:   __( 'Provides advanced layout overrides, performance enhancements, and custom nodes filtering for the WordPress admin bar.', 'core-functionality' ),
		);
	}
}
