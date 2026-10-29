<?php
/**
 * Aelia EU VAT Assistant for WooCommerce Verification Guard.
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
 * Class AeliaEuVatAssistant
 *
 * Reports whether Aelia EU VAT Assistant for WooCommerce is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class AeliaEuVatAssistant extends AbstractPlugin {

	/**
	 * Returns the environment id of Aelia EU VAT Assistant for WooCommerce.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'aelia-eu-vat-assistant';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'EU VAT Assistant';
	}

	/**
	 * Returns what must exist to prove Aelia EU VAT Assistant for WooCommerce has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'Aelia\WC\EU_VAT_Assistant\WC_Aelia_EU_VAT_Assistant' );
	}

	/**
	 * Returns the admin toolbar links Aelia EU VAT Assistant for WooCommerce offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-aelia-eu-vat-assistant',
				title:       __( 'EU VAT Assistant', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc_aelia_eu_vat_assistant' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'EU VAT Assistant', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'woocommerce',
			),
		);
	}
}
