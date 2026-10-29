<?php
/**
 * WooCommerce Customer/Order CSV Export Verification Guard.
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
 * Class WoocommerceCustomerOrderCsvExport
 */
class WoocommerceCustomerOrderCsvExport extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce-customer-order-csv-export';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'WC_Customer_Order_CSV_Export_Loader' );
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
				id:      'cf-woocommerce-csv-export',
				title:   __( 'Shop Order CSV Export', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc_customer_order_csv_export' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Shop Order CSV Export', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
