<?php
/**
 * WooCommerce PDF Invoices & Packing Slips Verification Guard.
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
 * Class WoocommercePdfInvoices
 */
class WoocommercePdfInvoices extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		// Full plugin name is WooCommerce PDF Invoices & Packing Slips.
		return 'woocommerce-pdf-invoices';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'WPO_WCPDF' );
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
				id:      'cf-woocommerce-pdf-invoices-settings',
				title:   __( 'PDF-invoice', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wpo_wcpdf_options_page' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce PDF-invoice Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
