<?php
/**
 * AutomateWoo Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class Automatewoo
 *
 * Reports whether AutomateWoo is active and started. AutomateWoo replaces WooCommerce
 * Follow-Up Emails on Hans's sites.
 *
 * @since 1.0.0
 */
class Automatewoo extends AbstractPlugin {

	/**
	 * Returns the environment id of AutomateWoo.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'automatewoo';
	}

	/**
	 * Returns what must exist to prove AutomateWoo has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// AutomateWoo only loads these (plugins_loaded 8) when its PHP, WordPress and WooCommerce
		// requirements are met, so they prove the plugin really started.
		return new ProofRequirement(
			classes:   'AutomateWoo',
			functions: 'AW'
		);
	}

	/**
	 * Returns the admin toolbar links AutomateWoo offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The first node is the menu head and goes to the workflows, like AutomateWoo's own menu
		// head; its id is in saved menus, so it keeps the old name. The others follow
		// AutomateWoo's own admin menu.
		return array(
			new ToolbarShortcut(
				id:          'cf-automatewoo',
				title:       __( 'AutomateWoo', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=aw_workflow' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Workflows', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-dashboard',
				title:       __( 'AutomateWoo Dashboard', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-dashboard' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Dashboard', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-workflows',
				title:       __( 'Workflows', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=aw_workflow' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Workflows', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-logs',
				title:       __( 'AutomateWoo Logs', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-logs' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Logs', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Logs', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-queue',
				title:       __( 'AutomateWoo Queue', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-queue' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Queue', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Queue', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-carts',
				title:       __( 'AutomateWoo Carts', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-carts' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Carts', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Carts', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-guests',
				title:       __( 'AutomateWoo Guests', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-guests' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Guests', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Guests', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-opt-ins',
				title:       __( 'AutomateWoo Opt-ins', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-opt-ins' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Opt-ins', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Opt-ins', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-reports',
				title:       __( 'AutomateWoo Reports', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-reports' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Reports', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Reports', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-tools',
				title:       __( 'AutomateWoo Tools', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-tools' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Tools', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Tools', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings',
				title:       __( 'AutomateWoo Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			// The tabs of AutomateWoo's settings page.
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-general',
				title:       __( 'AutomateWoo Settings: General', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=general' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: General', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'General', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-carts',
				title:       __( 'AutomateWoo Settings: Carts', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=carts' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: Carts', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Carts', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-mailchimp',
				title:       __( 'AutomateWoo Settings: MailChimp', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=mailchimp' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: MailChimp', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'MailChimp', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-campaign-monitor',
				title:       __( 'AutomateWoo Settings: Campaign Monitor', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=campaign-monitor' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: Campaign Monitor', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Campaign Monitor', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-active-campaign',
				title:       __( 'AutomateWoo Settings: ActiveCampaign', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=active-campaign' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: ActiveCampaign', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'ActiveCampaign', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-twilio',
				title:       __( 'AutomateWoo Settings: Twilio', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=twilio' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: Twilio', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Twilio', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-bitly',
				title:       __( 'AutomateWoo Settings: Bitly', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=bitly' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: Bitly', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Bitly', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-settings-status',
				title:       __( 'AutomateWoo Settings: Status', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-settings&tab=status' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Settings: Status', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Status', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-preview',
				title:       __( 'AutomateWoo Preview', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-preview' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Preview', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Preview', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-data-upgrade',
				title:       __( 'AutomateWoo Data Update', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=automatewoo-data-upgrade' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Data Update', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Data Update', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-automatewoo-manual-workflow-runner',
				title:       __( 'Manual Workflow Runner', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-admin&path=/automatewoo/manual-workflow-runner' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'AutomateWoo Manual Workflow Runner', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
