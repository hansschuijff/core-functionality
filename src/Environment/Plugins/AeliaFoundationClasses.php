<?php
/**
 * Aelia Foundation Classes for WooCommerce Verification Guard.
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
 * Class AeliaFoundationClasses
 *
 * Reports whether Aelia Foundation Classes for WooCommerce is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class AeliaFoundationClasses extends AbstractPlugin {

	/**
	 * Returns the environment id of Aelia Foundation Classes for WooCommerce.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'aelia-foundation-classes';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Aelia Foundation Classes';
	}

	/**
	 * Returns what must exist to prove Aelia Foundation Classes for WooCommerce has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'Aelia\WC\WC_AeliaFoundationClasses' );
	}

	/**
	 * Returns the admin toolbar links Aelia Foundation Classes for WooCommerce offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-aelia-foundation-classes',
				title:       __( 'Aelia Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=wc_aelia_foundation_classes' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Aelia Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Aelia', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}
}
