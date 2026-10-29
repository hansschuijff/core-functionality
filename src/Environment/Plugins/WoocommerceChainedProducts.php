<?php
/**
 * WooCommerce Chained Products Verification Guard.
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
 * Class WoocommerceChainedProducts
 *
 * Reports whether WooCommerce Chained Products is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class WoocommerceChainedProducts extends AbstractPlugin {

	/**
	 * Returns the environment id of WooCommerce Chained Products.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce-chained-products';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Chained Products';
	}

	/**
	 * Returns what must exist to prove WooCommerce Chained Products has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'WC_CP_PLUGIN_FILE' );
	}

	/**
	 * Returns the admin toolbar links WooCommerce Chained Products offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-woocommerce-chained-products',
				title:       __( 'Product Settings: Chained Products', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=wc_chained_products' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Chained Products', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Chained Products', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}
}
