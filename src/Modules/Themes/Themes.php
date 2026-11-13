<?php
/**
 * Themes Module Controller.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Themes
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Themes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class Themes
 *
 * Keeps the rest of the site in step with the active theme.
 *
 * @since 1.2.0
 */
class Themes extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.2.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'themes',
			name:        __( 'Themes', 'core-functionality' ),
			description: __( 'Keeps the site in step with the active theme.', 'core-functionality' ),
		);
	}
}
