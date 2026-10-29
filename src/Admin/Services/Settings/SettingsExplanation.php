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

use function esc_html;
use function implode;

/**
 * Class SettingsExplanation
 *
 * An explanation for a settings tab that can be folded away: a summary line over a few paragraphs,
 * optionally with a side note. It starts open, so a new visitor sees it, and closes with one click. A native <details> element, so it needs no script; its styling lives in the
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
	 * @param  bool     $open       Whether it starts open (the default, so new visitors see it).
	 * @return string The explanation (HTML).
	 */
	public static function render( string $summary, array $paragraphs, string $aside = '', bool $open = true ): string {
		$summary_html    = esc_html( $summary );
		$paragraphs_html = self::render_paragraphs( $paragraphs );
		$aside_html      = '' !== $aside ? self::render_aside( $aside ) : '';
		$open_attr       = $open ? ' open' : '';

		return '<details class="dwp-cf-explanation"' . $open_attr . '><summary><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> ' . $summary_html . '</summary>'
			. '<div class="dwp-cf-explanation-body">' . $paragraphs_html . $aside_html . '</div>'
			. '</details><!-- closes dwp-cf-explanation -->';
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
