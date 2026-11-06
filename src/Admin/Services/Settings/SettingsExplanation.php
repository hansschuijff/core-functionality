<?php
/**
 * Settings Explanation.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Admin\Services\Settings
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Admin\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use function array_shift;
use function esc_html;
use function implode;

/**
 * Class SettingsExplanation
 *
 * An explanation for a settings tab that can be folded open: a summary line with an info icon over a
 * few paragraphs, optionally with a side note. It starts closed, which keeps the tab short; the icon
 * and bold summary make it inviting enough to open (Hans, October 2026). A native <details> element, so it needs no script; its styling lives in the
 * settings page stylesheet (admin-settings.css), which loads on every tab.
 *
 * @since 1.0.0
 */
class SettingsExplanation {

	/**
	 * Returns the explanation.
	 *
	 * @since  1.0.0
	 * @param  string   $summary    The line that opens it, for example "Explanation".
	 * @param  string[] $paragraphs The paragraphs, as plain text.
	 * @param  string   $aside      A side note, as plain text, or ''.
	 * @param  bool     $open       Whether it starts open.
	 * @return string The explanation (HTML).
	 */
	public static function render( string $summary, array $paragraphs, string $aside = '', bool $open = false ): string {
		$summary_html    = esc_html( $summary );
		$paragraphs_html = self::render_paragraphs( $paragraphs );
		$aside_html      = '' !== $aside ? self::render_aside( $aside ) : '';
		$open_attr       = $open ? ' open' : '';

		return '<details class="dwp-cf-explanation"' . $open_attr . '><summary><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> ' . $summary_html . '</summary>'
			. '<div class="dwp-cf-explanation-body">' . $paragraphs_html . $aside_html . '</div>'
			. '</details><!-- closes dwp-cf-explanation -->';
	}

	/**
	 * Returns an explanation that lists items, each with a bold term, its paragraphs and an optional
	 * list of details: for example the features of a module with what each one does. Each item is an
	 * array with 'term' (string), 'paragraphs' (string[]) and optionally 'details' (string[]).
	 *
	 * @since  1.1.1
	 * @param  string $summary The line that opens it.
	 * @param  array  $items   The items, as plain text.
	 * @param  bool   $open    Whether it starts open.
	 * @return string The explanation (HTML).
	 */
	public static function render_list( string $summary, array $items, bool $open = false ): string {
		$summary_html = esc_html( $summary );
		$items_html   = array();
		foreach ( $items as $item ) {
			$items_html[] = self::render_list_item( $item );
		}
		$open_attr = $open ? ' open' : '';

		return '<details class="dwp-cf-explanation"' . $open_attr . '><summary><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> ' . $summary_html . '</summary>'
			. '<div class="dwp-cf-explanation-body"><ul class="dwp-cf-explanation-list">' . implode( '', $items_html ) . '</ul><!-- closes dwp-cf-explanation-list --></div>'
			. '</details><!-- closes dwp-cf-explanation -->';
	}

	/**
	 * Returns one item of a listing explanation: the term in bold before its first paragraph, the
	 * other paragraphs, and the details as a nested list.
	 *
	 * @since  1.1.1
	 * @param  array{term: string, paragraphs: string[], details?: string[]} $item The item, as plain text.
	 * @return string The item (HTML).
	 */
	private static function render_list_item( array $item ): string {
		$term_html    = '<strong>' . esc_html( $item['term'] ) . '</strong>';
		$paragraphs   = $item['paragraphs'];
		$first        = array_shift( $paragraphs ) ?? '';
		$first_html   = '<p>' . $term_html . ( '' !== $first ? ': ' . esc_html( $first ) : '' ) . '</p>';
		$details_html = self::render_details( $item['details'] ?? array() );

		return '<li>' . $first_html . self::render_paragraphs( $paragraphs ) . $details_html . '</li>';
	}

	/**
	 * Returns the details of a list item as a nested list, or '' when there are none.
	 *
	 * @since  1.1.1
	 * @param  string[] $details The details, as plain text.
	 * @return string The details (HTML).
	 */
	private static function render_details( array $details ): string {
		if ( empty( $details ) ) {
			return '';
		}

		$html = array();
		foreach ( $details as $detail ) {
			$html[] = '<li>' . esc_html( $detail ) . '</li>';
		}

		return '<ul class="dwp-cf-explanation-details">' . implode( '', $html ) . '</ul>';
	}

	/**
	 * Returns the paragraphs.
	 *
	 * @since  1.0.0
	 * @param  string[] $paragraphs The paragraphs, as plain text.
	 * @return string The paragraphs (HTML).
	 */
	private static function render_paragraphs( array $paragraphs ): string {
		$html = array();
		foreach ( $paragraphs as $paragraph ) {
			$html[] = '<p>' . esc_html( $paragraph ) . '</p>';
		}

		return implode( '', $html );
	}

	/**
	 * Returns the side note.
	 *
	 * @since  1.0.0
	 * @param  string $aside The side note, as plain text.
	 * @return string The side note (HTML).
	 */
	private static function render_aside( string $aside ): string {
		return '<aside class="dwp-cf-explanation-aside"><p>' . esc_html( $aside ) . '</p></aside>';
	}
}
