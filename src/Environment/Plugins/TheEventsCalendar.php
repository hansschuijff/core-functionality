<?php
/**
 * The Events Calendar Verification Guard.
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
 * Class TheEventsCalendar
 *
 * Validates the runtime availability of the core The Events Calendar environment
 * bypassing empty class hulls by invoking functional endpoint tests.
 *
 * @since 1.0.0
 */
class TheEventsCalendar extends AbstractPlugin {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'the-events-calendar';
	}

	/**
	 * Returns the post types The Events Calendar registers.
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	#[Override]
	public function get_post_types(): array {
		return array( 'tribe_events', 'tribe_venue', 'tribe_organizer', 'tec_calendar_embed', 'tribe-ea-record', 'deleted_event' );
	}

	/**
	 * Declares the extensive defensive proof criteria required for verification.
	 *
	 * Fills the declarative requirements. Single values do not need array wrappers.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The highly defensive proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement(
			classes:   'Tribe__Events__Main',
			functions: $this->get_defensive_template_tags()
		);
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this plugin can offer.
	 *
	 * Replaces get_raw_node_data by converting the link criteria into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of plugin toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-events-calendar',
				title:   __( 'Events', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_events' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Events', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Edit', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-edit-events',
				title:   __( 'All Events', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_events' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Events', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'events',
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-organizers',
				title:   __( 'Organizers', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_organizer' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Event Organizers', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'organizers',
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-venues',
				title:   __( 'Venues', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_venue' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Event Venues', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'venues',
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-categories',
				title:   __( 'Event Categories', 'core-functionality' ),
				href:    admin_url( 'edit-tags.php?taxonomy=tribe_events_cat&post_type=tribe_events' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Event Categories', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-settings',
				title:   __( 'Event Settings', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=tribe_events&page=tec-events-settings' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Events Calendar Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
				group:       'events',
			),
			// voorkant.
			new ToolbarShortcut(
				id:      'cf-events-calendar-view-events',
				title:   __( 'Events', 'core-functionality' ),
				href:    home_url( '/evenementen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'The Events Calendar', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-events-calendar-view-cat-family-constellations',
				title:   __( 'Family Constellations', 'core-functionality' ),
				href:    home_url( '/agenda/categorie/familieopstellingen/' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Family Constellations', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			// Pages of The Events Calendar, then its settings tabs (main tab before its sub-tabs).
			new ToolbarShortcut(
				id:          'cf-events-calendar-add-new',
				title:       __( 'Add Event', 'core-functionality' ),
				href:        admin_url( 'post-new.php?post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Add Event', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Add New', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-tags',
				title:       __( 'Event Tags', 'core-functionality' ),
				href:        admin_url( 'edit-tags.php?taxonomy=post_tag&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Event Tags', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Tags', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-import',
				title:       __( 'Event Import', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_events&page=aggregator' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Import', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Import', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-embeds',
				title:       __( 'Calendar Embeds', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tec_calendar_embed' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Calendar Embeds', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-help',
				title:       __( 'Events Help', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_events&page=tec-events-help-hub' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Help', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Help', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-troubleshooting',
				title:       __( 'Events Troubleshooting', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_events&page=tec-troubleshooting' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Troubleshooting', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Troubleshooting', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-add-ons',
				title:       __( 'Event Add-Ons', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_events&page=tribe-app-shop' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Event Add-Ons', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-setup-guide',
				title:       __( 'Events Setup Guide', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tribe_events&page=first-time-setup' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Setup Guide', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Setup Guide', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-general',
				title:       __( 'Event Settings: General', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=general&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'General', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'General', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-viewing',
				title:       __( 'Event Settings: Viewing', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=general-viewing-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Viewing', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Viewing', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-editing',
				title:       __( 'Event Settings: Editing', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=general-editing-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Editing', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Editing', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-maintenance',
				title:       __( 'Event Settings: Maintenance', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=general-maintenance-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Maintenance', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Maintenance', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-debugging',
				title:       __( 'Event Settings: Debugging', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=general-debugging-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Debugging', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Debugging', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-display',
				title:       __( 'Event Settings: Display', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Display', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Display', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-calendar',
				title:       __( 'Event Settings: Calendar', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-calendar-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Calendar', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Calendar', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-date-time',
				title:       __( 'Event Settings: Date & Time', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-date-time-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Date & Time', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Date & Time', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-currency',
				title:       __( 'Event Settings: Currency', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-currency-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Currency', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Currency', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-maps',
				title:       __( 'Event Settings: Maps', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-maps-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Maps', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Maps', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-additional-content',
				title:       __( 'Event Settings: Additional Content', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-additional-content-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Additional Content', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Additional Content', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-category-colors',
				title:       __( 'Event Settings: Category Colors', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-category-colors-settings&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Category Colors', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Category Colors', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-seo',
				title:       __( 'Event Settings: SEO & URLs', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=display-seo-settings&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'SEO & URLs', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'SEO & URLs', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-licenses',
				title:       __( 'Event Settings: Licenses', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=licenses&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Licenses', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Licenses', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-integrations',
				title:       __( 'Event Settings: Integrations', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=addons&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Integrations', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Integrations', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-google-maps',
				title:       __( 'Event Settings: Google Maps', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=google-maps&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), 'Google Maps' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Google Maps', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-import',
				title:       __( 'Event Settings: Import', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=integrations-import-tab&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Import', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Import', 'core-functionality' ),
				group:       'events',
			),
			new ToolbarShortcut(
				id:          'cf-events-calendar-settings-imports',
				title:       __( 'Event Settings: Imports', 'core-functionality' ),
				href:        admin_url( 'edit.php?page=tec-events-settings&tab=imports&post_type=tribe_events' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'The Events Calendar: %s', 'core-functionality' ), __( 'Imports', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Imports', 'core-functionality' ),
				group:       'events',
			),
		);
	}

	/**
	 * Retrieves the exact defensive collection of historical and modern template tags to test.
	 *
	 * @return string[] Names of crucial functions that must be loaded by PHP to verify readiness.
	 */
	private function get_defensive_template_tags(): array {
		return array(
			'tribe',
			'tribe_is_event',
			'tribe_is_venue',
			'tribe_is_organizer',
			'tribe_is_event_query',
			'tribe_is_event_category',
			'tribe_is_past_event',
			'tribe_is_upcoming',
			'tribe_is_showing_all',
			'tribe_is_by_date',
			'tribe_is_in_main_loop',
			'tribe_is_list_view',
			'tribe_is_past',
			'tribe_is_month',
			'tribe_is_day',
			'tec_is_full_site_editor',
			'tec_is_file_from_plugins',
			'tec_is_view',
		);
	}
}
