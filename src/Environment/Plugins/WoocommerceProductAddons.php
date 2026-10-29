<?php
/**
 * WooCommerce Product Add-Ons Verification Guard.
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
 * Class WoocommerceProductAddons
 */
class WoocommerceProductAddons extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce-product-addons';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'woocommerce_product_addons_init' );
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
				id:      'cf-woocommerce-product-addons',
				title:   __( 'Product Add-ons', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=product&page=addons' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce', 'core-functionality' ) . ' ' . __( 'Product Add-ons', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			// Its section in the product settings.
			new ToolbarShortcut(
				id:          'cf-woocommerce-product-addons-settings',
				title:       __( 'Product Settings: Add-ons', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=add_ons' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Add-ons', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Add-ons', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}
}
