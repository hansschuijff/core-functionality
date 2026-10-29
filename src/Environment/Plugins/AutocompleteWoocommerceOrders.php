<?php
/**
 * Autocomplete WooCommerce Orders Verification Guard.
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
 * Class AutocompleteWoocommerceOrders
 *
 * Reports whether Autocomplete WooCommerce Orders is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class AutocompleteWoocommerceOrders extends AbstractPlugin {

	/**
	 * Returns the environment id of Autocomplete WooCommerce Orders.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'autocomplete-woocommerce-orders';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Autocomplete Orders';
	}

	/**
	 * Returns what must exist to prove Autocomplete WooCommerce Orders has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'ACO_PLUGIN_FILE' );
	}

	/**
	 * Returns the admin toolbar links Autocomplete WooCommerce Orders offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-autocomplete-woocommerce-orders',
				title:       __( 'Autocomplete Orders Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=silkwave_aco' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Autocomplete Orders Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Autocomplete Orders', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}
}
