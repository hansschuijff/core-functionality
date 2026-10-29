<?php
/**
 * Duplicate Post Module Controller.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\DuplicatePost
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\DuplicatePost;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class DuplicatePost
 *
 * Adjustments to Yoast Duplicate Post.
 *
 * @since 1.0.0
 */
class DuplicatePost extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'duplicate-post',
			name:         __( 'Duplicate Post', 'core-functionality' ),
			description:  __( 'Adjustments to Yoast Duplicate Post.', 'core-functionality' ),
			dependencies: 'duplicate-post',
		);
	}
}
