<?php
/**
 * Events Module Controller.
 *
 * @package DeWittePrins\CoreFunctionality
 * @subpackage Modules\Events
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractModule;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

use function __;

/**
 * Class Events
 *
 * Additions to The Events Calendar and Event Tickets (each can run without the other).
 *
 * @since 1.0.0
 */
class Events extends AbstractModule {

	/**
	 * Returns the descriptive data of this module.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'events',
			name:         __( 'Events', 'core-functionality' ),
			description:  __( 'Additions to The Events Calendar and Event Tickets.', 'core-functionality' ),
			// Either plugin: a list of lists is "or" (a flat list would be "and").
			dependencies: array( 'or' => array( array( 'the-events-calendar' ), array( 'event-tickets' ) ) ),
		);
	}
}
