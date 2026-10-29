<?php
/**
 * WP Menu Cart Verification Guard.
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
 * Class WpMenuCart
 *
 * Reports whether WP Menu Cart is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class WpMenuCart extends AbstractPlugin {

	/**
	 * Returns the environment id of WP Menu Cart.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'wp-menu-cart';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Menu Cart';
	}

	/**
	 * Returns what must exist to prove WP Menu Cart has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'WpMenuCart' );
	}

	/**
	 * Returns the admin toolbar links WP Menu Cart offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-wp-menu-cart',
				title:       __( 'Menu Cart', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wpo_wpmenucart_options_page' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Menu Cart', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
