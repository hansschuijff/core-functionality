<?php
/**
 * Sensei LMS Module Controller.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Sensei
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Sensei;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;

use function __;

/**
 * Class Sensei
 *
 * Adjustments to Sensei LMS.
 *
 * @since 1.0.0
 */
class Sensei extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'sensei',
			name:         __( 'Sensei LMS', 'core-functionality' ),
			description:  __( 'Adjustments to Sensei LMS.', 'core-functionality' ),
			dependencies: 'sensei-lms',
			status:       Deployment::PRODUCTION,
		);
	}
}
