<?php
/**
 * Business Invoice Totals Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce\Features\BusinessInvoiceTotals
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\BusinessInvoiceTotals;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Tax;
use WC_Tax;

use function __;
use function add_action;
use function add_filter;
use function count;
use function esc_html;
use function file_get_contents;
use function in_array;
use function is_object;
use function is_readable;
use function remove_action;
use function remove_filter;
use function round;
use function sanitize_title;
use function sprintf;
use function str_contains;
use function trim;
use function wc_format_localized_decimal;
use function wc_get_order;
use function wc_price;
use function wpo_wcpdf_get_order_customer_vat_number;
use function WC;

/**
 * Class BusinessInvoiceTotals
 *
 * Makes the PDF invoice of a business order (an order with a customer VAT number) meet the Dutch invoice
 * requirements (article 35a Wet OB) in the totals: the order lines, then the total excluding VAT, the VAT
 * per rate with its percentage (and, with more than one rate, the amount excluding VAT per rate), the
 * total including VAT and the payment method.
 *
 * When the VAT is reverse-charged (a customer in another EU country, no VAT charged), the totals show
 * VAT 0% and a notice under them names the legal ground: an intra-Community supply for goods, a reverse
 * charge for services, with the customer's VAT number.
 *
 * Works with WooCommerce PDF Invoices & Packing Slips: invoices, and credit notes when its Pro version
 * makes them. Takes over Hans's earlier snippet for the totals and the reverse charge notice of the
 * legacy core-functionality plugin; their hooks are removed while this feature runs.
 *
 * @since 1.0.0
 */
class BusinessInvoiceTotals extends AbstractFeature {

	/**
	 * Document types of the PDF plugin that get the business totals.
	 *
	 * @var string[]
	 */
	private const DOCUMENT_TYPES = array( 'invoice', 'credit-note' );

	/**
	 * Key of the row with the total excluding VAT; the template also uses it as the row's CSS class.
	 *
	 * @var string
	 */
	private const ROW_EXCL_TAX = 'dwp-cf-total-excl-tax';

	/**
	 * Prefix of the key of a row with the amount excluding VAT of one rate.
	 *
	 * @var string
	 */
	private const ROW_TAX_BASE_PREFIX = 'dwp-cf-tax-base-';

	/**
	 * Key of the VAT 0% row of a reverse-charged order.
	 *
	 * @var string
	 */
	private const ROW_REVERSE_CHARGE = 'dwp-cf-reverse-charge';

	/**
	 * Types of the rows of get_order_item_totals() that this feature replaces.
	 *
	 * @var string[]
	 */
	private const REPLACED_ROW_TYPES = array( 'tax', 'total' );

	/**
	 * Keys of rows that this feature replaces or moves to the end.
	 *
	 * @var string[]
	 */
	private const REPLACED_ROW_KEYS = array( 'tax', 'order_total', 'payment_method' );

	/**
	 * Order meta of EU VAT Assistant with the result of the VAT number check.
	 *
	 * @var string
	 */
	private const META_VAT_NUMBER_VALIDATED = '_vat_number_validated';

	/**
	 * The value of META_VAT_NUMBER_VALIDATED for a valid number.
	 *
	 * @var string
	 */
	private const VAT_NUMBER_VALID = 'valid';

	/**
	 * Hooks this feature takes over: hook, callback, priority.
	 *
	 * Hans's snippet (totals and border styles) and the reverse charge notice of the legacy
	 * core-functionality plugin. Both add their hooks when they load, before CF launches (init 0).
	 *
	 * @var array<int, array{0: string, 1: string, 2: int}>
	 */
	private const TAKEN_OVER_HOOKS = array(
		array( 'wpo_wcpdf_woocommerce_totals', 'wpo_wcpdf_clean_b2b_invoice_totals', 10 ),
		array( 'wpo_wcpdf_custom_styles', 'wpo_wcpdf_b2b_collapse_borders', 10 ),
		array( 'wpo_wcpdf_after_order_details', 'DeWittePrins\CoreFunctionality\WooCommerce\render_tax_exempt_notice', 10 ),
	);

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'woocommerce_business_invoice_totals',
			name:         __( 'Business invoice totals', 'core-functionality' ),
			description:  __( 'On PDF invoices of orders with a customer VAT number: the total excluding VAT, the VAT per rate with its percentage and the total including VAT, plus a notice when the VAT is reverse-charged (Dutch invoice requirements).', 'core-functionality' ),
			dependencies: 'woocommerce-pdf-invoices',
			// It replaces a production snippet; it removes the snippet's hooks, but the site decides.
			confirm_activation: true,
			status:       Deployment::PRODUCTION,
		);
	}

	/**
	 * Removes the hooks it takes over and hooks into the totals, styles and order details of the PDF.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		$this->remove_taken_over_hooks();

		// Priority 20: after other changes to the rows, so the business layout is the last word.
		add_filter( 'wpo_wcpdf_woocommerce_totals', array( $this, 'filter_totals' ), 20, 3 );
		add_action( 'wpo_wcpdf_custom_styles', array( $this, 'print_styles' ), 10, 2 );
		add_action( 'wpo_wcpdf_after_order_details', array( $this, 'print_reverse_charge_notice' ), 10, 2 );
	}

	/**
	 * Removes the hooks of the snippet and the legacy plugin that this feature replaces.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function remove_taken_over_hooks(): void {
		foreach ( self::TAKEN_OVER_HOOKS as $hook ) {
			remove_filter( $hook[0], $hook[1], $hook[2] );
		}
	}

	/**
	 * Replaces the totals rows of a business document by the business layout.
	 *
	 * @since  1.0.0
	 * @param  array<string, array<string, string>> $totals        The rows: key => label and value.
	 * @param  mixed                                $order         The order (or refund) of the document.
	 * @param  mixed                                $document_type The document type, such as invoice.
	 * @return array<string, array<string, string>> The rows.
	 */
	public function filter_totals( array $totals, mixed $order, mixed $document_type ): array {
		$business_order = $this->get_business_order( $document_type, $order );
		if ( null === $business_order ) {
			return $totals;
		}

		return $this->build_totals( $totals, $order, $business_order );
	}

	/**
	 * Prints the styles of the business totals into the document's style block.
	 *
	 * @since  1.0.0
	 * @param  mixed $document_type The document type, such as invoice.
	 * @param  mixed $document      The document object of the PDF plugin.
	 * @return void
	 */
	public function print_styles( mixed $document_type, mixed $document ): void {
		if ( ! is_object( $document ) || null === $this->get_business_order( $document_type, $document->order ?? null ) ) {
			return;
		}

		$path = __DIR__ . '/assets/css/business-invoice-totals.css';
		if ( ! is_readable( $path ) ) {
			return;
		}

		echo (string) file_get_contents( $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This feature's own stylesheet; the PDF plugin escapes the style block as a whole.
	}

	/**
	 * Prints the reverse charge notice under the totals of a reverse-charged business document.
	 *
	 * @since  1.0.0
	 * @param  mixed $document_type The document type, such as invoice.
	 * @param  mixed $order         The order (or refund) of the document.
	 * @return void
	 */
	public function print_reverse_charge_notice( mixed $document_type, mixed $order ): void {
		$business_order = $this->get_business_order( $document_type, $order );
		if ( null === $business_order || ! $this->is_reverse_charged( $order, $business_order ) ) {
			return;
		}

		echo $this->render_reverse_charge_notice( $business_order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_reverse_charge_notice().
	}

	/**
	 * Returns the order with the customer's VAT data when the document is a business document.
	 *
	 * For a credit note the document's order is a refund; its VAT data is on the parent order.
	 *
	 * @since  1.0.0
	 * @param  mixed $document_type The document type, such as invoice.
	 * @param  mixed $order         The order (or refund) of the document.
	 * @return WC_Order|null The order, or null when this is not a business document.
	 */
	private function get_business_order( mixed $document_type, mixed $order ): ?WC_Order {
		if ( ! in_array( $document_type, self::DOCUMENT_TYPES, true ) || ! $order instanceof WC_Abstract_Order ) {
			return null;
		}

		$business_order = $order instanceof WC_Order ? $order : wc_get_order( $order->get_parent_id() );
		if ( ! $business_order instanceof WC_Order || '' === $this->get_customer_vat_number( $business_order ) ) {
			return null;
		}

		return $business_order;
	}

	/**
	 * Returns the customer's VAT number of an order.
	 *
	 * Uses the PDF plugin's lookup, which knows the order meta of the VAT plugins (such as vat_number of
	 * EU VAT Assistant and "VAT Number" of EU VAT Compliance).
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order.
	 * @return string The VAT number, or an empty string.
	 */
	private function get_customer_vat_number( WC_Order $order ): string {
		return trim( (string) wpo_wcpdf_get_order_customer_vat_number( $order ) );
	}

	/**
	 * Builds the business totals rows.
	 *
	 * @since  1.0.0
	 * @param  array<string, array<string, string>> $totals         The rows of the PDF plugin.
	 * @param  WC_Abstract_Order                    $order          The order (or refund) of the document.
	 * @param  WC_Order                             $business_order The order with the customer's VAT data.
	 * @return array<string, array<string, string>> The rows.
	 */
	private function build_totals( array $totals, WC_Abstract_Order $order, WC_Order $business_order ): array {
		$total     = (float) $order->get_total();
		$total_tax = (float) $order->get_total_tax();

		$rows = $this->get_line_rows( $totals );

		$rows[ self::ROW_EXCL_TAX ] = $this->make_row(
			__( 'Total excl. VAT', 'core-functionality' ),
			$this->format_price( $total - $total_tax, $order )
		);

		$rows += $this->get_tax_rows( $order );

		if ( $this->is_reverse_charged( $order, $business_order ) ) {
			$rows[ self::ROW_REVERSE_CHARGE ] = $this->make_row(
				__( 'VAT 0%', 'core-functionality' ),
				$this->format_price( 0.0, $order )
			);
		}

		$rows['order_total'] = $this->make_row(
			__( 'Total incl. VAT', 'core-functionality' ),
			$this->format_price( $total, $order )
		);

		if ( isset( $totals['payment_method'] ) ) {
			$rows['payment_method'] = $totals['payment_method'];
		}

		return $rows;
	}

	/**
	 * Returns the rows of the order lines: subtotal, discount, shipping, fees.
	 *
	 * Leaves out the VAT rows, the total and the payment method, which build_totals() adds again.
	 *
	 * @since  1.0.0
	 * @param  array<string, array<string, string>> $totals The rows of the PDF plugin.
	 * @return array<string, array<string, string>> The rows of the order lines.
	 */
	private function get_line_rows( array $totals ): array {
		$rows = array();
		foreach ( $totals as $key => $total ) {
			$is_replaced_type = in_array( $total['type'] ?? '', self::REPLACED_ROW_TYPES, true );
			$is_replaced_key  = in_array( (string) $key, self::REPLACED_ROW_KEYS, true );
			if ( $is_replaced_type || $is_replaced_key ) {
				continue;
			}
			$rows[ $key ] = $total;
		}

		return $rows;
	}

	/**
	 * Returns the VAT rows: per rate the VAT with its percentage.
	 *
	 * With more than one rate, each rate's amount excluding VAT comes before its VAT row, as the law
	 * asks for the amount per rate; with one rate that is the total excluding VAT above.
	 *
	 * @since  1.0.0
	 * @param  WC_Abstract_Order $order The order (or refund) of the document.
	 * @return array<string, array<string, string>> The VAT rows.
	 */
	private function get_tax_rows( WC_Abstract_Order $order ): array {
		$tax_totals = $order->get_tax_totals();
		$show_bases = count( $tax_totals ) > 1;

		$rows = array();
		foreach ( $tax_totals as $code => $tax ) {
			$rate_id = (int) $tax->rate_id;
			$percent = $this->get_rate_percent_label( $order, $rate_id );
			$key     = sanitize_title( (string) $code );

			if ( $show_bases ) {
				$rows[ self::ROW_TAX_BASE_PREFIX . $key ] = $this->make_row(
					$this->get_tax_base_label( $percent ),
					$this->format_price( $this->get_tax_base( $order, $rate_id ), $order )
				);
			}

			$rows[ $key ] = $this->make_row(
				$this->get_tax_label( (string) $tax->label, $percent ),
				$this->format_price( (float) $tax->amount, $order )
			);
		}

		return $rows;
	}

	/**
	 * Returns the label of a VAT row: the rate's name with its percentage, such as "BTW Hoog (21%)".
	 *
	 * @since  1.0.0
	 * @param  string $label   The rate's name.
	 * @param  string $percent The percentage, such as "21%", or an empty string when it is unknown.
	 * @return string The label.
	 */
	private function get_tax_label( string $label, string $percent ): string {
		if ( '' === $percent || str_contains( $label, $percent ) ) {
			return $label;
		}

		return sprintf( '%1$s (%2$s)', $label, $percent );
	}

	/**
	 * Returns the label of the row with a rate's amount excluding VAT.
	 *
	 * @since  1.0.0
	 * @param  string $percent The percentage, such as "21%", or an empty string when it is unknown.
	 * @return string The label.
	 */
	private function get_tax_base_label( string $percent ): string {
		/* translators: %s: VAT rate, such as 21%. */
		return trim( sprintf( __( 'Amount excl. VAT %s', 'core-functionality' ), $percent ) );
	}

	/**
	 * Returns the percentage of a tax rate as text, such as "21%" or "5,5%".
	 *
	 * Takes it from the order's own tax line, which keeps the rate the order was placed with; older
	 * orders without it fall back on the current rate.
	 *
	 * @since  1.0.0
	 * @param  WC_Abstract_Order $order   The order (or refund) of the document.
	 * @param  int               $rate_id The tax rate id.
	 * @return string The percentage, or an empty string when it is unknown.
	 */
	private function get_rate_percent_label( WC_Abstract_Order $order, int $rate_id ): string {
		$percent = null;
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( $tax_item instanceof WC_Order_Item_Tax && $tax_item->get_rate_id() === $rate_id ) {
				$percent = $tax_item->get_rate_percent();
				break;
			}
		}

		if ( null === $percent || '' === $percent ) {
			$percent = WC_Tax::get_rate_percent_value( $rate_id );
		}

		if ( ! $percent ) {
			return '';
		}

		return wc_format_localized_decimal( (string) round( (float) $percent, 4 ) ) . '%';
	}

	/**
	 * Returns the amount excluding VAT that a tax rate was charged over.
	 *
	 * The sum of the products, shipping and fees that carry this rate.
	 *
	 * @since  1.0.0
	 * @param  WC_Abstract_Order $order   The order (or refund) of the document.
	 * @param  int               $rate_id The tax rate id.
	 * @return float The amount.
	 */
	private function get_tax_base( WC_Abstract_Order $order, int $rate_id ): float {
		$base = 0.0;
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			$taxes = $item->get_taxes();
			if ( isset( $taxes['total'][ $rate_id ] ) && '' !== $taxes['total'][ $rate_id ] ) {
				$base += (float) $item->get_total();
			}
		}

		return $base;
	}

	/**
	 * Tells whether the VAT of a business document is reverse-charged.
	 *
	 * That is: a customer in another EU country than the shop, no VAT on a document with an amount,
	 * and a VAT number that EU VAT Assistant did not reject (when it checked it).
	 *
	 * @since  1.0.0
	 * @param  WC_Abstract_Order $order          The order (or refund) of the document.
	 * @param  WC_Order          $business_order The order with the customer's VAT data.
	 * @return bool True when the VAT is reverse-charged.
	 */
	private function is_reverse_charged( WC_Abstract_Order $order, WC_Order $business_order ): bool {
		if ( 0.0 !== round( (float) $order->get_total_tax(), 2 ) || 0.0 === round( (float) $order->get_total(), 2 ) ) {
			return false;
		}

		$country = $business_order->get_billing_country();
		$is_eu   = in_array( $country, WC()->countries->get_european_union_countries( 'eu_vat' ), true );
		if ( ! $is_eu || WC()->countries->get_base_country() === $country ) {
			return false;
		}

		$validated = (string) $business_order->get_meta( self::META_VAT_NUMBER_VALIDATED );

		return '' === $validated || self::VAT_NUMBER_VALID === $validated;
	}

	/**
	 * Renders the reverse charge notice.
	 *
	 * Goods are an intra-Community supply at 0% (article 138 of the VAT Directive), services are
	 * reverse-charged (article 196). Virtual products count as services, everything else as goods; an
	 * order with both gets both lines.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $business_order The order with the customer's VAT data.
	 * @return string The notice markup.
	 */
	private function render_reverse_charge_notice( WC_Order $business_order ): string {
		$supplies = $this->get_supply_kinds( $business_order );

		$lines = '';
		if ( $supplies['goods'] ) {
			$lines .= $this->render_notice_line( __( 'Intra-Community supply, VAT 0% (article 138 of VAT Directive 2006/112/EC).', 'core-functionality' ) );
		}
		if ( $supplies['services'] ) {
			$lines .= $this->render_notice_line( __( 'VAT reverse-charged (article 196 of VAT Directive 2006/112/EC).', 'core-functionality' ) );
		}
		$lines .= $this->render_notice_line(
			/* translators: %s: the customer's VAT number. */
			sprintf( __( 'Customer VAT number: %s', 'core-functionality' ), $this->get_customer_vat_number( $business_order ) )
		);

		return '<div class="dwp-cf-reverse-charge-notice">' . $lines . '</div><!-- closes dwp-cf-reverse-charge-notice -->';
	}

	/**
	 * Renders one line of the reverse charge notice.
	 *
	 * @since  1.0.0
	 * @param  string $text The text.
	 * @return string The line markup.
	 */
	private function render_notice_line( string $text ): string {
		return '<p>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Tells whether an order holds goods, services or both.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order.
	 * @return array{goods: bool, services: bool} What the order holds.
	 */
	private function get_supply_kinds( WC_Order $order ): array {
		$supplies = array(
			'goods'    => false,
			'services' => false,
		);

		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			if ( is_object( $product ) && $product->is_virtual() ) {
				$supplies['services'] = true;
			} else {
				$supplies['goods'] = true;
			}
		}

		return $supplies;
	}

	/**
	 * Makes a totals row.
	 *
	 * @since  1.0.0
	 * @param  string $label The label, without a colon (the PDF plugin removes those from its own rows).
	 * @param  string $value The amount as HTML.
	 * @return array<string, string> The row.
	 */
	private function make_row( string $label, string $value ): array {
		return array(
			'label' => $label,
			'value' => $value,
		);
	}

	/**
	 * Formats an amount in the order's currency.
	 *
	 * @since  1.0.0
	 * @param  float             $amount The amount.
	 * @param  WC_Abstract_Order $order  The order (or refund) of the document.
	 * @return string The price as HTML.
	 */
	private function format_price( float $amount, WC_Abstract_Order $order ): string {
		return wc_price( $amount, array( 'currency' => $order->get_currency() ) );
	}
}
