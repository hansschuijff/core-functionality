<?php
/**
 * EU VAT Compliance for WooCommerce Verification Guard.
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
 * Class EuVatCompliance
 *
 * Reports whether EU VAT Compliance for WooCommerce is active, and supplies the links it adds to WooCommerce.
 *
 * @since 1.0.0
 */
class EuVatCompliance extends AbstractPlugin {

	/**
	 * Returns the environment id of EU VAT Compliance for WooCommerce.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'eu-vat-compliance';
	}

	/**
	 * Returns the name the menu builder shows for this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'EU VAT Compliance';
	}

	/**
	 * Returns what must exist to prove EU VAT Compliance for WooCommerce has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'WC_VAT_COMPLIANCE_DIR' );
	}

	/**
	 * Returns the admin toolbar links EU VAT Compliance for WooCommerce offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-eu-vat-compliance',
				title:       __( 'VAT Compliance', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc_eu_vat_compliance_cc' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'VAT Compliance', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				group:       'woocommerce',
			),
		);
	}
}
