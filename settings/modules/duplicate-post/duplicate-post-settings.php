<?php
/**
 * Defaults of the Duplicate Post settings tab (module DuplicatePost, feature OwnDuplication).
 *
 * The plugins that duplicate their content themselves (all post types their guard names), and
 * loose post types of no known plugin; Duplicate Post shows no links for these. The tab saves its
 * choices as a whole; until then, these apply.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'own_duplication_providers'  => array(
		'the-events-calendar',
		'the-events-calendar-pro',
		'event-tickets',
		'event-tickets-plus',
		'sensei-lms',
	),
	'own_duplication_post_types' => array(),
);
