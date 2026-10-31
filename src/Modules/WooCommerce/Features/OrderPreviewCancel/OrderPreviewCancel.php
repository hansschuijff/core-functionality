<?php
/**
 * Order Preview Cancel Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce\Features\OrderPreviewCancel
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\OrderPreviewCancel;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Enums\Orientation;
use Override;
use WC_Order;

use function __;
use function add_action;
use function add_filter;
use function admin_url;
use function function_exists;
use function get_current_screen;
use function in_array;
use function plugins_url;
use function wcs_order_contains_subscription;
use function wp_add_inline_script;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_nonce_url;

/**
 * Class OrderPreviewCancel
 *
 * Adds a "Cancelled" button to the status buttons at the bottom of the order preview (the eye icon
 * in the order list), next to WooCommerce's own "Processing" and "Completed". It uses WooCommerce's
 * own status route (admin-ajax, woocommerce_mark_order_status), so stock, emails and order notes
 * follow the normal path.
 *
 * Completed orders can be cancelled too: that corrects a wrong click, and refunds are made by bank
 * transfer, so cancelling a paid order is only a status change here. The button has a red border
 * and asks for confirmation; for an order that started a subscription the question says the
 * subscription is cancelled as well, which cannot be undone.
 *
 * @since 1.0.0
 */
class OrderPreviewCancel extends AbstractFeature {

	/**
	 * Order statuses from which the order can be cancelled in the preview.
	 *
	 * @var string[]
	 */
	private const CANCELLABLE_STATUSES = array( 'pending', 'on-hold', 'processing', 'completed' );

	/**
	 * Screen ids of the order list: the legacy posts table and the HPOS table.
	 *
	 * @var string[]
	 */
	private const ORDER_LIST_SCREENS = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'woocommerce_order_preview_cancel',
			name:        __( 'Cancel button in the order preview', 'core-functionality' ),
			description: __( 'Adds a button to cancel the order to the status buttons of the order preview (the eye icon in the order list), with a confirmation.', 'core-functionality' ),
			orientation: Orientation::ADMIN,
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Hooks into the preview's actions and the order list's assets.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'woocommerce_admin_order_preview_actions', array( $this, 'add_cancel_action' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the cancel button to the "Change status" group of the preview.
	 *
	 * WooCommerce only calls this filter for users who may edit the order. A completed order has no
	 * status group of its own, so the group is created when it is missing.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $actions The preview actions, grouped.
	 * @param  WC_Order             $order   The order in the preview.
	 * @return array<string, mixed> The actions with the cancel button.
	 */
	public function add_cancel_action( array $actions, WC_Order $order ): array {
		if ( ! $order->has_status( self::CANCELLABLE_STATUSES ) ) {
			return $actions;
		}

		if ( ! isset( $actions['status'] ) ) {
			$actions['status'] = array(
				'group'   => __( 'Change status:', 'core-functionality' ),
				'actions' => array(),
			);
		}

		$actions['status']['actions']['cancel'] = $this->get_cancel_action( $order );

		return $actions;
	}

	/**
	 * Builds the cancel button in the format of wc_render_action_buttons().
	 *
	 * The action name becomes the button's class (wc-action-button-<action>); the script reads it
	 * to choose the confirmation text.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order in the preview.
	 * @return array<string, string> Url, name, title and action of the button.
	 */
	private function get_cancel_action( WC_Order $order ): array {
		$url = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=cancelled&order_id=' . $order->get_id() ),
			'woocommerce-mark-order-status'
		);

		return array(
			'url'    => $url,
			'name'   => __( 'Cancelled', 'core-functionality' ),
			'title'  => __( 'Change order status to cancelled', 'core-functionality' ),
			'action' => $this->starts_subscription( $order ) ? 'cancel-subscription' : 'cancel',
		);
	}

	/**
	 * Whether the order is the parent order of a subscription.
	 *
	 * Cancelling a parent order makes WooCommerce Subscriptions cancel its subscriptions as well;
	 * cancelling a renewal order does not.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order in the preview.
	 * @return bool True when cancelling the order also cancels a subscription.
	 */
	private function starts_subscription( WC_Order $order ): bool {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return wcs_order_contains_subscription( $order, 'parent' );
	}

	/**
	 * Loads the button style and the confirmation script on the order list.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_assets(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, self::ORDER_LIST_SCREENS, true ) ) {
			return;
		}

		$version = $this->plugin->get_data( 'version' );

		wp_enqueue_style(
			'dwp-cf-order-preview-cancel',
			plugins_url( 'assets/css/order-preview-cancel.css', __FILE__ ),
			array(),
			$version
		);

		wp_enqueue_script(
			'dwp-cf-order-preview-cancel',
			plugins_url( 'assets/js/order-preview-cancel.js', __FILE__ ),
			array(),
			$version,
			array( 'in_footer' => true )
		);

		wp_add_inline_script(
			'dwp-cf-order-preview-cancel',
			'window.dwpCfOrderPreviewCancel = ' . wp_json_encode( $this->get_script_texts() ) . ';',
			'before'
		);
	}

	/**
	 * Returns the confirmation texts for the script.
	 *
	 * @since  1.0.0
	 * @return array<string, string> The question for an order and for an order with a subscription.
	 */
	private function get_script_texts(): array {
		return array(
			'confirmOrder'        => __( 'Cancel this order?', 'core-functionality' ),
			'confirmSubscription' => __( 'Cancel this order? The subscription of this order will be cancelled as well, and that cannot be undone.', 'core-functionality' ),
		);
	}
}
