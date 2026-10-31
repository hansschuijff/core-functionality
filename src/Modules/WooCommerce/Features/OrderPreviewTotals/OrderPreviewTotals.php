<?php
/**
 * Order Preview Totals Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce\Features\OrderPreviewTotals
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\OrderPreviewTotals;

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
use function get_current_screen;
use function in_array;
use function plugins_url;
use function sanitize_html_class;
use function wp_enqueue_style;
use function wp_kses_post;

/**
 * Class OrderPreviewTotals
 *
 * Shows the order totals under the products in the order preview (the eye icon in the order list):
 * subtotal, discount, shipping, fees, tax and total, the same rows as on the invoice and in the
 * order emails (WC_Order::get_order_item_totals()). The payment method row is left out; the preview
 * already shows it.
 *
 * Its stylesheet also lets the content of the preview scroll, so the preview with its buttons always
 * fits on the screen.
 *
 * @since 1.0.0
 */
class OrderPreviewTotals extends AbstractFeature {

	/**
	 * Key of the totals markup in the preview data (data.<key> in the preview template).
	 *
	 * @var string
	 */
	private const DATA_KEY = 'dwp_cf_totals_html';

	/**
	 * Rows of get_order_item_totals() that are not shown.
	 *
	 * @var string[]
	 */
	private const SKIPPED_ROWS = array( 'payment_method' );

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
			id:          'woocommerce_order_preview_totals',
			name:        __( 'Totals in the order preview', 'core-functionality' ),
			description: __( 'Shows subtotal, discount, shipping, tax and total under the products in the order preview (the eye icon in the order list), and lets the preview scroll so it fits on the screen.', 'core-functionality' ),
			orientation: Orientation::ADMIN,
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Hooks into the preview's data and template and the order list's assets.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'add_totals_to_details' ), 10, 2 );
		add_action( 'woocommerce_admin_order_preview_end', array( $this, 'print_totals_placeholder' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the totals markup to the data WooCommerce sends to the preview.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $details The preview data.
	 * @param  WC_Order             $order   The order in the preview.
	 * @return array<string, mixed> The preview data with the totals.
	 */
	public function add_totals_to_details( array $details, WC_Order $order ): array {
		$details[ self::DATA_KEY ] = $this->render_totals( $order );

		return $details;
	}

	/**
	 * Prints the place of the totals in the preview template, after the products.
	 *
	 * The template is an Underscore template; the triple braces insert the markup unescaped, it is
	 * escaped in render_totals().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function print_totals_placeholder(): void {
		echo '{{{ data.' . self::DATA_KEY . ' }}}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed template placeholder.
	}

	/**
	 * Renders the totals table of the order.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order in the preview.
	 * @return string The table, or an empty string when there are no totals.
	 */
	private function render_totals( WC_Order $order ): string {
		$rows = '';
		foreach ( $order->get_order_item_totals() as $key => $total ) {
			if ( in_array( $key, self::SKIPPED_ROWS, true ) ) {
				continue;
			}
			$rows .= $this->render_total_row( (string) $key, (string) ( $total['label'] ?? '' ), (string) ( $total['value'] ?? '' ) );
		}

		if ( '' === $rows ) {
			return '';
		}

		return $this->render_totals_wrapper_open() . $rows . $this->render_totals_wrapper_close();
	}

	/**
	 * Renders the opening of the totals table.
	 *
	 * Uses WooCommerce's own preview table classes, so it looks like the products table above it.
	 *
	 * @since  1.0.0
	 * @return string The opening markup.
	 */
	private function render_totals_wrapper_open(): string {
		return '<div class="wc-order-preview-table-wrapper dwp-cf-order-preview-totals">'
			. '<table cellspacing="0" class="wc-order-preview-table"><tbody>';
	}

	/**
	 * Renders one row of the totals table.
	 *
	 * @since  1.0.0
	 * @param  string $key   The row key, such as cart_subtotal, discount, shipping or order_total.
	 * @param  string $label The label, with its colon.
	 * @param  string $value The amount as HTML, such as wc_price() output.
	 * @return string The row markup.
	 */
	private function render_total_row( string $key, string $label, string $value ): string {
		$class_attr = sanitize_html_class( 'dwp-cf-total-' . $key );
		$label_html = wp_kses_post( $label );
		$value_html = wp_kses_post( $value );

		return <<<HTML
			<tr class="{$class_attr}">
				<th scope="row">{$label_html}</th>
				<td>{$value_html}</td>
			</tr>
			HTML;
	}

	/**
	 * Renders the closing of the totals table.
	 *
	 * @since  1.0.0
	 * @return string The closing markup.
	 */
	private function render_totals_wrapper_close(): string {
		return '</tbody></table>'
			. '</div><!-- closes dwp-cf-order-preview-totals -->';
	}

	/**
	 * Loads the totals and scroll styles on the order list.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_assets(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, self::ORDER_LIST_SCREENS, true ) ) {
			return;
		}

		wp_enqueue_style(
			'dwp-cf-order-preview-totals',
			plugins_url( 'assets/css/order-preview-totals.css', __FILE__ ),
			array(),
			$this->plugin->get_data( 'version' )
		);
	}
}
