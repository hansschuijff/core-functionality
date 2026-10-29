<?php
/**
 * WooCommerce Subscriptions Verification Guard.
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
use function function_exists;
use function home_url;
use function wc_get_page_permalink;
use function is_wp_error;
use function get_term_by;
use function get_term_link;

/**
 * Class WoocommerceSubscriptions
 */
class WoocommerceSubscriptions extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce-subscriptions';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'WC_Subscriptions' );
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
				id:      'cf-woocommerce-subscriptions',
				title:   __( 'Subscriptions', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=shop_subscription' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Subscriptions', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			// Frontend Links - Safe lazy loading via PHP short closures!
			new ToolbarShortcut(
				id:      'cf-woocommerce-subscriptions-view-my-account',
				title:   __( 'My Account: Subscriptions', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_endpoint_url( 'subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( __( '/myaccount/subscriptions/', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account: Subscriptions', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'Subscriptions', 'core-functionality' ),
				group:       'my-account',
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-subscriptions-view-course-subscriptions',
				title:   __( 'Course Payment Plans', 'core-functionality' ),
				href:    fn() => $this->get_product_cat_url_by_slug( __( 'course-payment-plans', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Course Payment Plans', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			// Its tab in the WooCommerce settings.
			new ToolbarShortcut(
				id:          'cf-woocommerce-subscriptions-settings',
				title:       __( 'Subscription Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=subscriptions' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Subscription Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Subscriptions', 'core-functionality' ),
				group:       'woocommerce',
			),
		);
	}

	/**
	 * Returns the front-end URL of a product category.
	 *
	 * @since  1.0.0
	 * @param  string $category_slug The product category slug.
	 * @return string The category URL, or '#' when the category does not exist.
	 */
	private function get_product_cat_url_by_slug( string $category_slug ): string {
		if ( empty( $category_slug ) ) {
			return '#';
		}
		$category = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $category ) {
			return '#';
		}
		$category_url = get_term_link( $category, 'product_cat' );

		return is_wp_error( $category_url ) ? '#' : $category_url;
	}
}
