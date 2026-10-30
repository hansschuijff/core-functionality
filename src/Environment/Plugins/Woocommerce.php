<?php
/**
 * WooCommerce Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;
use WC_Tax;

use function __;
use function admin_url;
use function array_merge;
use function class_exists;
use function function_exists;
use function home_url;
use function method_exists;
use function rawurlencode;
use function sanitize_key;
use function sprintf;
use function wc_get_cart_url;
use function wc_get_checkout_url;
use function wc_get_page_permalink;
use function get_term_by;
use function get_term_link;
use function is_wp_error;

/**
 * Class Woocommerce
 */
class Woocommerce extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'woocommerce';
	}

	/**
	 * Returns the post types WooCommerce registers.
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	#[Override]
	public function get_post_types(): array {
		return array( 'product', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_order_placehold', 'shop_coupon' );
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement(
			classes: 'WooCommerce',
			actions: 'woocommerce_loaded'
		);
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		$nodes = array(
			// Admin Area Links - Safe to call directly because admin_url is purely string manipulation.
			new ToolbarShortcut(
				id:      'cf-woocommerce-orders',
				title:   __( 'Shop Orders', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=shop_order' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Orders', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Orders', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-products',
				title:   __( 'Products', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=product' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Products', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-coupons',
				title:   __( 'Shop Coupons', 'core-functionality' ),
				href:    admin_url( 'edit.php?post_type=shop_coupon' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Coupons', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Coupons', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-product-cats',
				title:   __( 'Product Categories', 'core-functionality' ),
				href:    admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Product Categories', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Categories', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-analytics',
				title:   __( 'Shop Statistics', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-admin&path=/analytics/revenue' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Sales Statistics', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Statistics', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-settings',
				title:   __( 'WooCommerce Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=wc-settings' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			// Frontend Links - Safe lazy loading via PHP short closures!
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-shop',
				title:   __( 'Shop', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( __( '/shop', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Shop', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-my-account',
				title:   __( 'My Account', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( __( '/myaccount', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				group:       'my-account',
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-my-account-orders',
				title:   __( 'My Account: Orders', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( __( '/myaccount/orders/', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account: Orders', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'Orders', 'core-functionality' ),
				group:       'my-account',
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-my-account-downloads',
				title:   __( 'My Account: Downloads', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_endpoint_url( 'downloads', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( __( '/myaccount/downloads/', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account: Downloads', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'Downloads', 'core-functionality' ),
				group:       'my-account',
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-my-account-edit-address',
				title:   __( 'My Account: Addresses', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( __( '/myaccount/edit-address/', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account: Addresses', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'Addresses', 'core-functionality' ),
				group:       'my-account',
			),
			new ToolbarShortcut(
				id:      'cf-woocommerce-view-my-account-edit-account',
				title:   __( 'My Account: Account details', 'core-functionality' ),
				href:    fn() => function_exists( 'wc_get_page_permalink' ) ? wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( __( '/myaccount/edit-account/', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'My Account: Account details', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
				short_title: __( 'Account details', 'core-functionality' ),
				group:       'my-account',
			),
			new ToolbarShortcut(
				title:   __( 'Courses', 'core-functionality' ),
				id:      'cf-woocommerce-view-shop-courses',
				href:    fn() => $this->get_product_cat_url_by_slug( __( 'cursus-opleiding', 'core-functionality' ) ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Courses', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			// The WooCommerce menu, its settings tabs and their sections (tax classes: see get_tax_class_nodes()).
			new ToolbarShortcut(
				id:          'cf-woocommerce',
				title:       'WooCommerce',
				href:        admin_url( 'edit.php?post_type=shop_order' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . 'WooCommerce',
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-home',
				title:       __( 'WooCommerce Home', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-admin' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Home', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Home', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-customers',
				title:       __( 'Customers', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-admin&path=/customers' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Customers', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-reports',
				title:       __( 'Shop Reports', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-reports' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Shop Reports', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Reports', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-status',
				title:       __( 'WooCommerce Status', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-status' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Status', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Status', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-extensions',
				title:       __( 'WooCommerce Extensions', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-admin&path=/extensions' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Extensions', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Extensions', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-general',
				title:       __( 'WooCommerce Settings: General', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=general' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: General', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'General', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products',
				title:       __( 'WooCommerce Settings: Products', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Products', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Products', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products-general',
				title:       __( 'Product Settings: General', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: General', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'General', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products-inventory',
				title:       __( 'Product Settings: Inventory', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Inventory', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Inventory', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products-downloadable',
				title:       __( 'Product Settings: Downloadable Products', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=downloadable' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Downloadable Products', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Downloadable Products', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products-download-urls',
				title:       __( 'Product Settings: Approved Download Directories', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=download_urls' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Approved Download Directories', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Approved Download Directories', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-products-advanced',
				title:       __( 'Product Settings: Advanced', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=products&section=advanced' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Product Settings: Advanced', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Advanced', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-tax',
				title:       __( 'WooCommerce Settings: Tax', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=tax' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Tax', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Tax', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-tax-options',
				title:       __( 'Tax Settings: Options', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=tax&section=' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Tax Settings: Options', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Tax Options', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-tax-standard',
				title:       __( 'Tax Settings: Standard Rates', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=tax&section=standard' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Tax Settings: Standard Rates', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Standard Rates', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-shipping',
				title:       __( 'WooCommerce Settings: Shipping', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Shipping', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Shipping', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-shipping-zones',
				title:       __( 'Shipping Settings: Zones', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Shipping Settings: Zones', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Shipping Zones', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-shipping-options',
				title:       __( 'Shipping Settings: Options', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Shipping Settings: Options', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Shipping Options', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-shipping-classes',
				title:       __( 'Shipping Settings: Classes', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Shipping Settings: Classes', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Classes', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-payments',
				title:       __( 'WooCommerce Settings: Payments', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Payments', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Payments', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-accounts',
				title:       __( 'WooCommerce Settings: Accounts & Privacy', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=account' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Accounts & Privacy', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Accounts & Privacy', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-emails',
				title:       __( 'WooCommerce Settings: Emails', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=email' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Emails', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Emails', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-integration',
				title:       __( 'WooCommerce Settings: Integration', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=integration' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Integration', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Integration', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-integration-maxmind',
				title:       __( 'MaxMind Geolocation', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=integration&section=maxmind_geolocation' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'MaxMind Geolocation', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-site-visibility',
				title:       __( 'WooCommerce Settings: Site Visibility', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=site-visibility' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Site Visibility', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Site Visibility', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-point-of-sale',
				title:       __( 'WooCommerce Settings: Point of Sale', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=point-of-sale' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Point of Sale', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Point of Sale', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced',
				title:       __( 'WooCommerce Settings: Advanced', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Settings: Advanced', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Advanced', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-page-setup',
				title:       __( 'WooCommerce Advanced: Page Setup', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: Page Setup', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Page Setup', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-rest-api',
				title:       __( 'WooCommerce Advanced: REST API', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: REST API', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'REST API', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-webhooks',
				title:       __( 'WooCommerce Advanced: Webhooks', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=webhooks' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: Webhooks', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Webhooks', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-woocommerce-com',
				title:       __( 'WooCommerce Advanced: WooCommerce.com', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=woocommerce_com' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: WooCommerce.com', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'WooCommerce.com', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-blueprint',
				title:       __( 'WooCommerce Advanced: Blueprint', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=blueprint' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: Blueprint', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Blueprint', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-settings-advanced-features',
				title:       __( 'WooCommerce Advanced: Features', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Advanced: Features', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Features', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-scheduled-actions',
				title:       __( 'Scheduled Actions', 'core-functionality' ),
				path:        'tools.php?page=action-scheduler',
			),
		);

		return array_merge(
			$nodes,
			$this->get_tax_class_nodes(),
			$this->get_product_nodes(),
			$this->get_analytics_nodes(),
			$this->get_marketing_nodes(),
			$this->get_checkout_nodes()
		);
	}

	/**
	 * Returns the pages of the Products menu besides the product list and categories.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The menu items.
	 */
	private function get_product_nodes(): array {
		return array(
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-product-new',
				title:       __( 'New Product', 'core-functionality' ),
				path:        'post-new.php?post_type=product',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-product-brands',
				title:       __( 'Product Brands', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=product_brand&post_type=product',
				short_title: __( 'Brands', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-product-tags',
				title:       __( 'Product Tags', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=product_tag&post_type=product',
				short_title: __( 'Tags', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-product-attributes',
				title:       __( 'Product Attributes', 'core-functionality' ),
				path:        'edit.php?post_type=product&page=product_attributes',
				short_title: __( 'Attributes', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-product-reviews',
				title:       __( 'Product Reviews', 'core-functionality' ),
				path:        'edit.php?post_type=product&page=product-reviews',
				short_title: __( 'Reviews', 'core-functionality' ),
			),
		);
	}

	/**
	 * Returns the reports of WooCommerce Analytics; "Shop Statistics" (cf-woocommerce-analytics) opens
	 * the revenue report.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The menu items.
	 */
	private function get_analytics_nodes(): array {
		$reports = array(
			'overview'   => array( __( 'Shop Statistics: Overview', 'core-functionality' ), __( 'Overview', 'core-functionality' ) ),
			'products'   => array( __( 'Shop Statistics: Products', 'core-functionality' ), __( 'Products', 'core-functionality' ) ),
			'revenue'    => array( __( 'Shop Statistics: Revenue', 'core-functionality' ), __( 'Revenue', 'core-functionality' ) ),
			'orders'     => array( __( 'Shop Statistics: Orders', 'core-functionality' ), __( 'Orders', 'core-functionality' ) ),
			'variations' => array( __( 'Shop Statistics: Variations', 'core-functionality' ), __( 'Variations', 'core-functionality' ) ),
			'categories' => array( __( 'Shop Statistics: Categories', 'core-functionality' ), __( 'Categories', 'core-functionality' ) ),
			'coupons'    => array( __( 'Shop Statistics: Coupons', 'core-functionality' ), __( 'Coupons', 'core-functionality' ) ),
			'taxes'      => array( __( 'Shop Statistics: Taxes', 'core-functionality' ), __( 'Taxes', 'core-functionality' ) ),
			'downloads'  => array( __( 'Shop Statistics: Downloads', 'core-functionality' ), __( 'Downloads', 'core-functionality' ) ),
			'stock'      => array( __( 'Shop Statistics: Stock', 'core-functionality' ), __( 'Stock', 'core-functionality' ) ),
			'settings'   => array( __( 'Shop Statistics: Settings', 'core-functionality' ), __( 'Statistics Settings', 'core-functionality' ) ),
		);

		$nodes = array();
		foreach ( $reports as $report => list( $title, $short_title ) ) {
			$nodes[] = ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-analytics-' . $report,
				title:       $title,
				path:        'admin.php?page=wc-admin&path=/analytics/' . $report,
				short_title: $short_title,
			);
		}

		return $nodes;
	}

	/**
	 * Returns the Marketing menu of WooCommerce (its coupons are cf-woocommerce-coupons).
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The menu items.
	 */
	private function get_marketing_nodes(): array {
		return array(
			ToolbarShortcut::to_admin_page(
				id:          'cf-woocommerce-marketing',
				title:       __( 'Shop Marketing', 'core-functionality' ),
				path:        'admin.php?page=wc-admin&path=/marketing',
				short_title: __( 'Marketing', 'core-functionality' ),
			),
		);
	}

	/**
	 * Returns the cart and checkout pages on the site (the shop and my account are in the main list).
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The menu items.
	 */
	private function get_checkout_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-woocommerce-view-cart',
				title:       __( 'Cart', 'core-functionality' ),
				href:        fn() => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( __( '/cart', 'core-functionality' ) ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Cart', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
			new ToolbarShortcut(
				id:          'cf-woocommerce-view-checkout',
				title:       __( 'Checkout', 'core-functionality' ),
				href:        fn() => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( __( '/checkout', 'core-functionality' ) ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WooCommerce Checkout', 'core-functionality' ),
				orientation: Orientation::FRONTEND,
			),
		);
	}

	/**
	 * Returns a menu item per tax class of this site, besides the standard rates ("Reduced rate",
	 * "Zero rate", or classes of the site's own). Read from WooCommerce, so only when it is loaded.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The menu items.
	 */
	private function get_tax_class_nodes(): array {
		if ( ! class_exists( 'WC_Tax' ) || ! method_exists( 'WC_Tax', 'get_tax_rate_classes' ) ) {
			return array();
		}

		$nodes = array();
		foreach ( WC_Tax::get_tax_rate_classes() as $tax_class ) {
			$nodes[] = new ToolbarShortcut(
				id:          'cf-woocommerce-settings-tax-' . sanitize_key( $tax_class->slug ),
				/* translators: %s: name of a tax class. */
				title:       sprintf( __( 'Tax Settings: %s Rates', 'core-functionality' ), $tax_class->name ),
				href:        admin_url( 'admin.php?page=wc-settings&tab=tax&section=' . rawurlencode( $tax_class->slug ) ),
				/* translators: %s: name of a tax class. */
				label:       __( 'Link to', 'core-functionality' ) . ' ' . sprintf( __( 'Tax Settings: %s Rates', 'core-functionality' ), $tax_class->name ),
				orientation: Orientation::ADMIN,
				/* translators: %s: name of a tax class. */
				short_title: sprintf( __( '%s Rates', 'core-functionality' ), $tax_class->name ),
			);
		}

		return $nodes;
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
