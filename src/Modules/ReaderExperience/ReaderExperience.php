<?php
/**
 * Reader Experience Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality
 * @subpackage Modules\ReaderExperience
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\ReaderExperience;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class ReaderExperience
 *
 * Settings for the Reader Experience plugin, which itself is steered by filters only.
 *
 * @since 1.0.0
 */
class ReaderExperience extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'reader-experience',
			name:         __( 'Reader Experience', 'core-functionality' ),
			description:  __( 'Settings for the Reader Experience plugin: heading anchors, reading time and feedback.', 'core-functionality' ),
			dependencies: 'reader-experience',
		);
	}
}
