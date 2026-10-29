<?php
/**
 * Event Fields for the AI Access abilities.
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

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\FieldHandlerInterface;
use Override;
use Tribe__Events__API;
use Tribe__Events__Timezones;
use WP_Error;
use WP_Post;

use function __;
use function array_map;
use function get_post_meta;
use function get_post_type;
use function gmdate;
use function is_array;
use function sanitize_text_field;
use function sprintf;
use function strtotime;
use function substr;
use function tribe;
use function tribe_event_is_all_day;
use function tribe_get_organizer_ids;
use function tribe_get_venue_id;

/**
 * Class EventFields
 *
 * The data of an event outside its content: start, end, all day, time zone, venue, organizers
 * and cost. Saved through the event repository of The Events Calendar, which also keeps the UTC
 * dates and the duration up to date.
 *
 * @since 1.0.0
 */
class EventFields implements FieldHandlerInterface {

	/**
	 * Returns the key of this group under "fields".
	 *
	 * @since  1.0.0
	 * @return string The id.
	 */
	#[Override]
	public function get_id(): string {
		return 'event';
	}

	/**
	 * Returns whether this group belongs to a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True for events.
	 */
	#[Override]
	public function applies_to( string $post_type ): bool {
		return 'tribe_events' === $post_type;
	}

	/**
	 * Returns the JSON schema of the values this group accepts.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	#[Override]
	public function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'description'          => 'Events only. Dates in the event\'s own time zone. Existing venues and organizers only, by id; see core-functionality/list-venues-and-organizers.',
			'additionalProperties' => false,
			'properties'           => array(
				'start'         => array(
					'type'        => 'string',
					'description' => 'Start, as YYYY-MM-DD HH:MM.',
				),
				'end'           => array(
					'type'        => 'string',
					'description' => 'End, as YYYY-MM-DD HH:MM.',
				),
				'all_day'       => array( 'type' => 'boolean' ),
				'timezone'      => array(
					'type'        => 'string',
					'description' => 'For example Europe/Amsterdam.',
				),
				'venue_id'      => array( 'type' => 'integer' ),
				'organizer_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'cost'          => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Returns the current values of an event.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The event.
	 * @return array The values.
	 */
	#[Override]
	public function read( WP_Post $post ): array {
		// The stored dates, not the template tags: those give "now" for an event without dates.
		return array(
			'start'         => substr( (string) get_post_meta( $post->ID, '_EventStartDate', true ), 0, 16 ),
			'end'           => substr( (string) get_post_meta( $post->ID, '_EventEndDate', true ), 0, 16 ),
			'all_day'       => (bool) tribe_event_is_all_day( $post->ID ),
			'timezone'      => Tribe__Events__Timezones::get_event_timezone_string( $post->ID ),
			'venue_id'      => (int) tribe_get_venue_id( $post->ID ),
			'organizer_ids' => array_map( 'intval', (array) tribe_get_organizer_ids( $post->ID ) ),
			'cost'          => (string) get_post_meta( $post->ID, '_EventCost', true ),
		);
	}

	/**
	 * Saves the given values on an event.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post   The event, already saved.
	 * @param  array   $values The values from the input.
	 * @return true|WP_Error True, or the reason why it failed.
	 */
	#[Override]
	public function write( WP_Post $post, array $values ): true|WP_Error {
		$changes = $this->validate( $values );
		if ( $changes instanceof WP_Error ) {
			return $changes;
		}

		if ( array() === $changes ) {
			return true;
		}

		// The event API saves the whole set, so the current values fill in what is not given.
		$merged = $changes + $this->read( $post );
		if ( '' === $merged['start'] || '' === $merged['end'] ) {
			return new WP_Error( 'cf_ai_event_dates', __( 'This event has no dates yet; give both start and end.', 'core-functionality' ) );
		}

		$saved = Tribe__Events__API::saveEventMeta( $post->ID, $this->get_event_data( $post, $merged ), $post );
		if ( true !== $saved ) {
			return new WP_Error( 'cf_ai_event_save', __( 'The event data could not be saved.', 'core-functionality' ) );
		}

		return true;
	}

	/**
	 * Checks the given values and cleans them up.
	 *
	 * @since  1.0.0
	 * @param  array $values The values from the input.
	 * @return array|WP_Error The given values, clean, or the reason why they are refused.
	 */
	private function validate( array $values ): array|WP_Error {
		$clean = array();

		foreach ( array( 'start', 'end' ) as $key ) {
			if ( ! isset( $values[ $key ] ) ) {
				continue;
			}
			if ( false === strtotime( (string) $values[ $key ] ) ) {
				/* translators: %s: the field name. */
				return new WP_Error( 'cf_ai_event_date', sprintf( __( 'The event field "%s" is not a valid date.', 'core-functionality' ), $key ) );
			}
			$clean[ $key ] = sanitize_text_field( (string) $values[ $key ] );
		}

		if ( isset( $values['all_day'] ) ) {
			$clean['all_day'] = (bool) $values['all_day'];
		}
		if ( isset( $values['timezone'] ) ) {
			$clean['timezone'] = sanitize_text_field( (string) $values['timezone'] );
		}
		if ( isset( $values['cost'] ) ) {
			$clean['cost'] = sanitize_text_field( (string) $values['cost'] );
		}

		if ( isset( $values['venue_id'] ) ) {
			$venue_id = (int) $values['venue_id'];
			if ( 0 !== $venue_id && 'tribe_venue' !== get_post_type( $venue_id ) ) {
				/* translators: %d: post id. */
				return new WP_Error( 'cf_ai_event_venue', sprintf( __( 'There is no venue %d.', 'core-functionality' ), $venue_id ) );
			}
			$clean['venue_id'] = $venue_id;
		}

		if ( isset( $values['organizer_ids'] ) && is_array( $values['organizer_ids'] ) ) {
			$organizer_ids = array_map( 'intval', $values['organizer_ids'] );
			foreach ( $organizer_ids as $organizer_id ) {
				if ( 'tribe_organizer' !== get_post_type( $organizer_id ) ) {
					/* translators: %d: post id. */
					return new WP_Error( 'cf_ai_event_organizer', sprintf( __( 'There is no organizer %d.', 'core-functionality' ), $organizer_id ) );
				}
			}
			$clean['organizer_ids'] = $organizer_ids;
		}

		return $clean;
	}

	/**
	 * Returns the data for the event API, keeping what this group does not set (map, featured,
	 * shown in the calendar, hidden from upcoming) as it is, since that API resets what it misses.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post   The event.
	 * @param  array   $values All values of this group: the given ones over the current ones.
	 * @return array The data for Tribe__Events__API::saveEventMeta().
	 */
	private function get_event_data( WP_Post $post, array $values ): array {
		$start = strtotime( $values['start'] );
		$end   = strtotime( $values['end'] );

		$venue = array( 'VenueID' => $values['venue_id'] );
		if ( get_post_meta( $post->ID, '_EventShowMap', true ) ) {
			$venue['EventShowMap'] = '1';
		}
		if ( get_post_meta( $post->ID, '_EventShowMapLink', true ) ) {
			$venue['EventShowMapLink'] = '1';
		}

		$data = array(
			'EventStartDate' => gmdate( 'Y-m-d', $start ),
			'EventStartTime' => gmdate( 'H:i:s', $start ),
			'EventEndDate'   => gmdate( 'Y-m-d', $end ),
			'EventEndTime'   => gmdate( 'H:i:s', $end ),
			'EventAllDay'    => $values['all_day'] ? 'yes' : 'no',
			'EventTimezone'  => $values['timezone'],
			'EventCost'      => $values['cost'],
			'venue'          => $venue,
			'organizer'      => array( 'OrganizerID' => $values['organizer_ids'] ),
		);

		if ( get_post_meta( $post->ID, '_EventHideFromUpcoming', true ) ) {
			$data['EventHideFromUpcoming'] = 'yes';
		}
		if ( -1 === (int) $post->menu_order ) {
			$data['EventShowInCalendar'] = 'yes';
		}
		if ( tribe( 'tec.featured_events' )->is_featured( $post->ID ) ) {
			$data['feature_event'] = '1';
		}

		return $data;
	}
}
