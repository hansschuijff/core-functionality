<?php
/**
 * AI Access Module Controller.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class AiAccess
 *
 * What an AI assistant (such as Claude through the MCP Adapter) may do on this site: abilities
 * of the WordPress Abilities API, each with its own permission check.
 *
 * @since 1.0.0
 */
class AiAccess extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'ai-access',
			name:        __( 'AI Access', 'core-functionality' ),
			description: __( 'Abilities an AI assistant may use on this site, for example to write drafts. Publishing stays with you.', 'core-functionality' ),
		);
	}
}
