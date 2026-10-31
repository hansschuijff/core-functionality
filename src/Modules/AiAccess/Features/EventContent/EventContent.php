<?php
/**
 * AI Event Content Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\EventContent
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\EventContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentServices;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentTypeProfile;
use Override;

use function __;
use function add_filter;
use function is_array;

/**
 * Class EventContent
 *
 * Makes events of The Events Calendar available to an AI assistant: the event post type with its
 * categories and tags, and its dates, venue, organizers and cost as extra fields. The blocks that
 * hold an event's data and tickets must stay in the content. Tickets themselves are left alone.
 *
 * @since 1.0.0
 */
class EventContent extends AbstractFeature {

	/**
	 * The blocks of an event that must stay once they are in the content.
	 *
	 * @var string[]
	 */
	private const PROTECTED_BLOCKS = array(
		'tribe/event-datetime',
		'tribe/event-venue',
		'tribe/event-organizer',
		'tribe/event-price',
		'tribe/tickets',
		'tribe/rsvp',
		'tribe/attendees',
		'dewitteprins/remote-tickets',
	);

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'ai_access_event_content',
			name:          __( 'Event Content', 'core-functionality' ),
			description:   __( 'An AI assistant may write drafts of events (The Events Calendar), with dates, venue, organizers and cost. Tickets are left alone.', 'core-functionality' ),
			dependencies:  'the-events-calendar',
			uses_features: array( ContentEditing::class ),
			status:        Deployment::PRODUCTION,
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'core-functionality/ai-access-content-types', array( $this, 'add_content_type' ) );
		add_filter( 'core-functionality/ai-access-field-handlers', array( $this, 'add_field_handler' ) );
		add_filter( 'core-functionality/ai-access-abilities', array( $this, 'add_ability' ), 10, 2 );
	}

	/**
	 * Adds the ability that lists venues and organizers.
	 *
	 * @since  1.0.0
	 * @param  mixed           $abilities The abilities.
	 * @param  ContentServices $services  The shared content services.
	 * @return array The abilities.
	 */
	public function add_ability( mixed $abilities, ContentServices $services ): array {
		$abilities   = is_array( $abilities ) ? $abilities : array();
		$abilities[] = new ListVenuesAndOrganizers( $services );

		return $abilities;
	}

	/**
	 * Adds the event post type. A Rewrite & Republish copy stays off until it is tested for events.
	 *
	 * @since  1.0.0
	 * @param  mixed $profiles The profiles.
	 * @return array The profiles.
	 */
	public function add_content_type( mixed $profiles ): array {
		$profiles = is_array( $profiles ) ? $profiles : array();

		$profiles['tribe_events'] = new ContentTypeProfile(
			'tribe_events',
			taxonomies:       array( 'tribe_events_cat', 'post_tag' ),
			protected_blocks: self::PROTECTED_BLOCKS,
			allows_rewrite:   false,
		);

		return $profiles;
	}

	/**
	 * Adds the event fields.
	 *
	 * @since  1.0.0
	 * @param  mixed $handlers The handlers.
	 * @return array The handlers.
	 */
	public function add_field_handler( mixed $handlers ): array {
		$handlers   = is_array( $handlers ) ? $handlers : array();
		$handlers[] = new EventFields();

		return $handlers;
	}
}
