<?php
/**
 * Invoice Details Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\WooCommerce\Features\InvoiceDetails
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\InvoiceDetails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;
use WC_DateTime;
use WC_Order;

use function __;
use function add_action;
use function add_filter;
use function esc_html;
use function esc_html__;
use function file_get_contents;
use function is_object;
use function is_readable;
use function method_exists;
use function trim;
use function wc_format_datetime;
use function wpo_wcpdf_sanitize_html_content;

/**
 * Class InvoiceDetails
 *
 * Details of the PDF invoices of WooCommerce PDF Invoices & Packing Slips, for every order:
 *
 * - When the order is paid, a "Paid on" row with the payment date in the order data, and no due date.
 *   An unpaid invoice keeps the due date of the PDF plugin's own setting, called "Pay before".
 * - The customer's note from the checkout under the totals, over the full width, instead of in the
 *   narrow column next to the totals.
 * - Room between the logo and the shop's address.
 *
 * Takes over the payment rows of the legacy plugin, which could show "Waiting for payment" next to
 * "Paid on" because it read the status and the payment date apart. Its notes to the customer (from
 * the order management) are not ported: they are messages such as "payment received", not part of an
 * invoice. LegacyHandover stops the legacy PDF file while this feature runs.
 *
 * @since 1.0.0
 */
class InvoiceDetails extends AbstractFeature {

	/**
	 * Document type of the PDF plugin that gets the payment details and the moved note.
	 *
	 * @var string
	 */
	private const DOCUMENT_TYPE = 'invoice';

	/**
	 * The customer notes that were taken out of the column next to the totals, by order id.
	 *
	 * Each holds the note as the PDF plugin made it and the title of the notes block.
	 *
	 * @var array<int, array{note: string, title: string}>
	 */
	private array $moved_notes = array();

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:           'woocommerce_invoice_details',
			name:         __( 'Invoice details', 'core-functionality' ),
			description:  __( 'On PDF invoices: the payment date when the order is paid (and then no due date), the customer\'s note under the totals over the full width, and room between the logo and the address.', 'core-functionality' ),
			dependencies: 'woocommerce-pdf-invoices',
		);
	}

	/**
	 * Hooks into the order data, the due date, the customer notes and the styles of the PDF.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action( 'wpo_wcpdf_after_order_data', array( $this, 'print_payment_date_row' ), 10, 2 );
		add_filter( 'wpo_wcpdf_document_due_date', array( $this, 'filter_due_date' ), 10, 2 );
		add_filter( 'wpo_wcpdf_document_due_date_title', array( $this, 'filter_due_date_title' ), 10, 2 );
		add_filter( 'wpo_wcpdf_shipping_notes', array( $this, 'move_customer_note' ), 10, 2 );
		// Priority 20: after the reverse charge notice of the business totals, which belongs to the totals.
		add_action( 'wpo_wcpdf_after_order_details', array( $this, 'print_moved_customer_note' ), 20, 2 );
		add_action( 'wpo_wcpdf_custom_styles', array( $this, 'print_styles' ), 10, 2 );
	}

	/**
	 * Prints the "Paid on" row in the order data of the invoice of a paid order.
	 *
	 * @since  1.0.0
	 * @param  mixed $document_type The document type, such as invoice.
	 * @param  mixed $order         The order of the document.
	 * @return void
	 */
	public function print_payment_date_row( mixed $document_type, mixed $order ): void {
		if ( self::DOCUMENT_TYPE !== $document_type || ! $order instanceof WC_Order ) {
			return;
		}

		$date_paid = $this->get_payment_date( $order );
		if ( null === $date_paid ) {
			return;
		}

		echo $this->render_payment_date_row( $date_paid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_payment_date_row().
	}

	/**
	 * Leaves out the due date of the invoice of a paid order.
	 *
	 * @since  1.0.0
	 * @param  mixed $due_date The due date as a timestamp, 0 for none.
	 * @param  mixed $document The document object of the PDF plugin.
	 * @return mixed The due date, or 0 when the order is paid.
	 */
	public function filter_due_date( mixed $due_date, mixed $document ): mixed {
		$order = $this->get_invoice_order( $document );
		if ( null === $order || ! $this->is_paid( $order ) ) {
			return $due_date;
		}

		return 0;
	}

	/**
	 * Calls the due date of an invoice "Pay before", which says what the customer has to do.
	 *
	 * @since  1.0.0
	 * @param  mixed $title    The title, "Due Date:" by default.
	 * @param  mixed $document The document object of the PDF plugin.
	 * @return mixed The title.
	 */
	public function filter_due_date_title( mixed $title, mixed $document ): mixed {
		if ( null === $this->get_invoice_order( $document ) ) {
			return $title;
		}

		return __( 'Pay before:', 'core-functionality' );
	}

	/**
	 * Takes the customer's note out of the column next to the totals, to print it under them.
	 *
	 * @since  1.0.0
	 * @param  mixed $notes    The customer's note as HTML, or an empty string.
	 * @param  mixed $document The document object of the PDF plugin.
	 * @return mixed An empty string for an invoice with a note, otherwise the note it got.
	 */
	public function move_customer_note( mixed $notes, mixed $document ): mixed {
		$order = $this->get_invoice_order( $document );
		if ( null === $order || '' === trim( (string) $notes ) ) {
			return $notes;
		}

		// Protected the way the PDF plugin protects its own notes, as the document is decoded once more.
		$note = method_exists( $document, 'protect_from_document_decode' ) ? $document->protect_from_document_decode( (string) $notes ) : (string) $notes;

		$this->moved_notes[ $order->get_id() ] = array(
			'note'  => (string) $note,
			'title' => method_exists( $document, 'get_customer_notes_title' ) ? (string) $document->get_customer_notes_title() : '',
		);

		return '';
	}

	/**
	 * Prints the customer's note under the totals, when it was taken out of the column next to them.
	 *
	 * @since  1.0.0
	 * @param  mixed $document_type The document type, such as invoice.
	 * @param  mixed $order         The order of the document.
	 * @return void
	 */
	public function print_moved_customer_note( mixed $document_type, mixed $order ): void {
		if ( self::DOCUMENT_TYPE !== $document_type || ! $order instanceof WC_Order ) {
			return;
		}

		$moved_note = $this->moved_notes[ $order->get_id() ] ?? null;
		if ( null === $moved_note ) {
			return;
		}

		echo $this->render_customer_note( $moved_note['note'], $moved_note['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped and sanitized in render_customer_note().
	}

	/**
	 * Prints the styles of the invoice details into the document's style block.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function print_styles(): void {
		$path = __DIR__ . '/assets/css/invoice-details.css';
		if ( ! is_readable( $path ) ) {
			return;
		}

		echo (string) file_get_contents( $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This feature's own stylesheet; the PDF plugin escapes the style block as a whole.
	}

	/**
	 * Returns the order of an invoice document.
	 *
	 * @since  1.0.0
	 * @param  mixed $document The document object of the PDF plugin.
	 * @return WC_Order|null The order, or null when the document is not an invoice of an order.
	 */
	private function get_invoice_order( mixed $document ): ?WC_Order {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_type' ) || self::DOCUMENT_TYPE !== $document->get_type() ) {
			return null;
		}

		$order = $document->order ?? null;

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Tells whether an order is paid: it has a payment date, or a status that WooCommerce counts as paid.
	 *
	 * Either one is enough. The legacy plugin read them apart, so a paid order that later went on hold
	 * showed "Waiting for payment" next to its payment date.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order.
	 * @return bool True when the order is paid.
	 */
	private function is_paid( WC_Order $order ): bool {
		return null !== $order->get_date_paid() || $order->is_paid();
	}

	/**
	 * Returns the payment date of a paid order.
	 *
	 * An order with a paid status but without a payment date (marked paid by hand) falls back on its
	 * completion date.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order The order.
	 * @return WC_DateTime|null The date, or null when the order is not paid or has no date.
	 */
	private function get_payment_date( WC_Order $order ): ?WC_DateTime {
		if ( ! $this->is_paid( $order ) ) {
			return null;
		}

		return $order->get_date_paid() ?? $order->get_date_completed();
	}

	/**
	 * Renders the "Paid on" row of the order data table.
	 *
	 * @since  1.0.0
	 * @param  WC_DateTime $date_paid The payment date.
	 * @return string The row markup.
	 */
	private function render_payment_date_row( WC_DateTime $date_paid ): string {
		$label = esc_html__( 'Paid on:', 'core-functionality' );
		$date  = esc_html( wc_format_datetime( $date_paid ) );

		return <<<HTML
			<tr class="dwp-cf-payment-date">
				<th>{$label}</th>
				<td>{$date}</td>
			</tr><!-- closes dwp-cf-payment-date -->
			HTML;
	}

	/**
	 * Renders the customer's note under the totals.
	 *
	 * Keeps the PDF plugin's own class for the block, so it looks like the plugin's notes.
	 *
	 * @since  1.0.0
	 * @param  string $note  The note as HTML, as the PDF plugin made it.
	 * @param  string $title The title of the notes block.
	 * @return string The note markup.
	 */
	private function render_customer_note( string $note, string $title ): string {
		$title_html = '' === $title ? '' : '<h3>' . esc_html( $title ) . '</h3>';
		$note_html  = wpo_wcpdf_sanitize_html_content( $note, 'notes' );

		return <<<HTML
			<div class="customer-notes dwp-cf-customer-note">
				{$title_html}
				{$note_html}
			</div><!-- closes dwp-cf-customer-note -->
			HTML;
	}
}
