<?php
/**
 * Genesis Connect for WooCommerce Verification Guard.
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
 * Class GenesisConnectWoocommerce
 *
 * Reports whether Genesis Connect for WooCommerce is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class GenesisConnectWoocommerce extends AbstractPlugin {

	/**
	 * Returns the environment id of Genesis Connect for WooCommerce.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'genesis-connect-woocommerce';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Genesis Connect';
	}

	/**
	 * Returns what must exist to prove Genesis Connect for WooCommerce has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'GCW_DIR' );
	}

	/**
	 * Returns the admin toolbar links Genesis Connect for WooCommerce offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-genesis-connect-woocommerce',
				title:       __( 'Genesis Connect Add-ons', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=gencwooc' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Genesis Connect Add-ons', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'woocommerce',
			),
		);
	}
}
