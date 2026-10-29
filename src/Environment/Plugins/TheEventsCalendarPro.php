<?php
/**
 * The Events Calendar Pro Verification Guard.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Plugins
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;
use function sprintf;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class TheEventsCalendarPro
 *
 * Validates the runtime availability of the core The Events Calendar Pro environment
 * bypassing empty class hulls by invoking functional endpoint tests.
 *
 * @since 1.0.0
 */
class TheEventsCalendarPro extends AbstractPlugin {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'the-events-calendar-pro';
	}

	/**
	 * Returns the post types Events Calendar Pro registers.
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	#[Override]
	public function get_post_types(): array {
		return array( 'tribe_event_series' );
	}

	/**
	 * Declares the extensive defensive proof criteria required for Pro verification.
	 *
	 * Fills the declarative requirements matrix including specialized Pro functions and core classes.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The highly defensive proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement(
			classes:   array( 'Tribe__Events__Main', 'Tribe__Events__Pro__Main' ),
			functions: $this->get_defensive_pro_template_tags()
		);
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this plugin can offer.
	 *
	 * Transforms your link data into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of plugin toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-events-calendar-pro',
				title:   __( 'Events Calendar Pro', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_events&page=tec-events-settings' ),
				label:   __( 'The Events Calendar Pro Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'events',
			),
			// Pages Events Calendar Pro adds, then its settings tabs.
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-series',
				title:       __( 'Series', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_event_series' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Series', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-venue-categories',
				title:       __( 'Venue Categories', 'core-functionality' ),
				href:        admin_url( 'edit-tags.php?taxonomy=tec_venue_category&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Venue Categories', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
				group:       'venues',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-organizer-categories',
				title:       __( 'Organizer Categories', 'core-functionality' ),
				href:        admin_url( 'edit-tags.php?taxonomy=tec_organizer_category&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Organizer Categories', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
				group:       'organizers',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-organizers',
				title:       __( 'Event Settings: Organizers', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-organizers-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Organizers', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Organizers', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-defaults',
				title:       __( 'Event Settings: Default Content', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=defaults&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Default Content', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Default Content', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-default-venue',
				title:       __( 'Event Settings: Default Venue', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=defaults-venue-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Default Venue', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Default Venue', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-default-organizer',
				title:       __( 'Event Settings: Default Organizer', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=defaults-organizer-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Default Organizer', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Default Organizer', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-additional-fields',
				title:       __( 'Event Settings: Additional Fields', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=additional-fields&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Additional Fields', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Additional Fields', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-meetings',
				title:       __( 'Event Settings: Meetings', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=meetings&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), __( 'Meetings', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Meetings', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-power-automate',
				title:       __( 'Event Settings: Power Automate', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=power-automate&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), 'Power Automate' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Power Automate', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-pro-settings-zapier',
				title:       __( 'Event Settings: Zapier', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=zapier&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Events Calendar Pro: %s', 'core-functionality' ), 'Zapier' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Zapier', 'core-functionality' ),
				group:       'events',
			),
		);
	}

	/**
	 * Retrieves the exact defensive collection of historical and modern template tags for Pro to test.
	 *
	 * @return string[] Names of crucial functions that must be loaded by PHP to verify readiness.
	 */
	private function get_defensive_pro_template_tags(): array {
		return array(
			'tribe',
			// Defined in: events-calendar-pro/src/functions/template-tags/general.php.
			'tribe_is_week',
			'tribe_is_map',
			'tribe_is_photo',
			'tec_is_venue_view',
			'tec_is_organizer_view',
			// Defined in: events-calendar-pro/events-calendar-pro.php and general.php.
			'tribe_is_recurring_event',
			// Defined in: events-calendar-pro/src/functions/template-tags/series.php.
			'tribe_is_event_series',
		);
	}
}
