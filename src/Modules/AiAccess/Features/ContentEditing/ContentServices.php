<?php
/**
 * Content Services of the AI Access abilities.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ContentServices
 *
 * The shared parts every content ability works with, made once per request by the feature and
 * handed to each ability, also to the abilities other features add.
 *
 * @since 1.0.0
 */
readonly class ContentServices {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ContentTypes  $types    The content types that may be used.
	 * @param FieldHandlers $handlers The groups of extra fields.
	 * @param ContentReader $reader   Turns posts into data.
	 * @param ContentWriter $writer   Saves data into posts, within the rules.
	 */
	public function __construct(
		public ContentTypes $types,
		public FieldHandlers $handlers,
		public ContentReader $reader,
		public ContentWriter $writer,
	) {}
}
