<?php
/**
 * WooCommerce ActiveCampaign (ActiveWoo) Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class WoocommerceActiveCampaignIntegration
 */
class WoocommerceActiveCampaignIntegration extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce-active-campaign-integration';
	}

	/**
	 * Returns the name the plugin is marketed by (its header has a much longer one).
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'ActiveWoo';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'WC_Active_Woo' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-activewoo',
				title:   __( 'ActiveWoo', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce ActiveWoo Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-activewoo-base',
				title:   __( 'ActiveWoo Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce ActiveWoo Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-activewoo-advanced',
				title:   __( 'Advanced Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo-advanced' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce ActiveWoo Advanced Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-activewoo-rc',
				title:   __( 'Recover Cart Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo-advanced-rc' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce ActiveWoo Recover Cart Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-activewoo-coupons',
				title:   __( 'Coupons Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo-advanced-coupons' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce ActiveWoo Coupons Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			// Its license section.
			new ToolbarShortcut(
				id:          'cf-activewoo-license',
				title:       __( 'ActiveWoo License', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=integration&section=active-woo-license' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'ActiveWoo License', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'License', 'core-functionality' ),
			),
		);
	}
}
