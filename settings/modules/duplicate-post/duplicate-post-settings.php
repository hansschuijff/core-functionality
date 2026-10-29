<?php
/**
 * Defaults of the Duplicate Post settings tab (module DuplicatePost, feature OwnDuplication).
 *
 * The post types whose own plugin duplicates them; Duplicate Post shows no links for these. The tab
 * saves its choices as a whole; until then, these apply.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'own_duplication_post_types' => array(
		// The Events Calendar (Pro).
		'tribe_events',
		'tribe_event_series',
		// Event Tickets (Plus).
		'tec_tc_ticket',
		'tribe_rsvp_tickets',
		'tribe_wooticket',
		// Sensei LMS.
		'course',
		'lesson',
		'quiz',
		'question',
		'multiple_question',
	),
);
