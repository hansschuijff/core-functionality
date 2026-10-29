<?php
/**
 * Event Tickets Core Extension Verification Guard.
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
 * Class EventTickets
 *
 * Inspects execution states checking if the core Event Tickets plugin
 * is fully operational utilizing the translation mapping pipeline.
 *
 * @since 1.0.0
 */
class EventTickets extends AbstractPlugin {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * Must return 'event-tickets' to align with the core preflight registry mappings.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'event-tickets';
	}

	/**
	 * Returns the post types Event Tickets registers (Tickets Commerce and RSVP).
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	#[Override]
	public function get_post_types(): array {
		return array( 'tec_tc_ticket', 'tec_tc_order', 'tec_tc_attendee', 'tribe_rsvp_tickets', 'tribe_rsvp_attendees' );
	}

	/**
	 * Declares the specific technical proof criteria required for Event Tickets core verification.
	 *
	 * Matches the core class string layout and attaches the defensive version conflict firewall.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The structured proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement(
			classes:   'Tribe__Tickets__Main',
			functions: array( 'tribe_tickets', 'tribe_events_has_tickets', 'tribe_tickets_parent_post' )
		);
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this plugin can offer.
	 *
	 * Transforms your link criteria directly into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of plugin toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-event-tickets',
				title:   __( 'Event Tickets', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=tec-tickets-settings' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Event Tickets Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
				group:       'tickets',
			),
			// The Tickets menu of Event Tickets.
			new ToolbarShortcut(
				id:          'cf-event-tickets-tickets',
				title:       __( 'Tickets', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-admin-tickets' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Tickets', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-home',
				title:       __( 'Tickets Home', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Home', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Home', 'core-functionality' ),
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-all-tickets',
				title:       __( 'All Tickets', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-admin-tickets' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'All Tickets', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Overview', 'core-functionality' ),
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-attendees',
				title:       __( 'Attendees', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-attendees' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Attendees', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-coupons-fees',
				title:       __( 'Coupons & Fees', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-order-modifiers' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Coupons & Fees', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-orders',
				title:       __( 'Ticket Orders', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=tec_tc_order' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Orders', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Orders', 'core-functionality' ),
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-help',
				title:       __( 'Tickets Help', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-help-hub' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Help', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Help', 'core-functionality' ),
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-troubleshooting',
				title:       __( 'Tickets Troubleshooting', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tec-tickets-troubleshooting' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Troubleshooting', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Troubleshooting', 'core-functionality' ),
				group:       'tickets',
			),
			new ToolbarShortcut(
				id:          'cf-event-tickets-setup-guide',
				title:       __( 'Tickets Setup Guide', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=tickets-setup' ),
				/* translators: %s: title of the page or settings tab. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Event Tickets: %s', 'core-functionality' ), __( 'Setup Guide', 'core-functionality' ) ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Setup Guide', 'core-functionality' ),
				group:       'tickets',
			),
		);
	}
}
