<?php
/**
 * Mollie Payments for WooCommerce Verification Guard.
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
 * Class MolliePaymentsForWoocommerce
 *
 * Reports whether Mollie Payments for WooCommerce is active and loaded, and supplies its tab in the
 * WooCommerce settings with its sections.
 *
 * @since 1.0.0
 */
class MolliePaymentsForWoocommerce extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'mollie-payments-for-woocommerce';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'M4W_FILE' );
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
			// Its tab in the WooCommerce settings, with its sections.
			new ToolbarShortcut(
				id:          'cf-mollie',
				title:       __( 'Mollie Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=mollie_settings' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Mollie Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Mollie', 'core-functionality' ),
				group:       'woocommerce',
			),
			new ToolbarShortcut(
				id:          'cf-mollie-api-keys',
				title:       __( 'Mollie: API Keys', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=mollie_settings&section=mollie_api_keys' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Mollie: API Keys', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'API Keys', 'core-functionality' ),
				group:       'woocommerce',
			),
			new ToolbarShortcut(
				id:          'cf-mollie-payment-methods',
				title:       __( 'Mollie: Payment Methods', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=mollie_settings&section=mollie_payment_methods' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Mollie: Payment Methods', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Payment Methods', 'core-functionality' ),
				group:       'woocommerce',
			),
			new ToolbarShortcut(
				id:          'cf-mollie-advanced',
				title:       __( 'Mollie: Advanced Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=mollie_settings&section=mollie_advanced' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Mollie: Advanced Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Advanced Settings', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}
}
