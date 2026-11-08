<?php
/**
 * Content Replacer of the AI Access abilities.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.1.3
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;

use function __;
use function array_merge;
use function array_reverse;
use function array_values;
use function implode;
use function is_string;
use function sprintf;
use function strlen;
use function strpos;
use function substr_replace;
use function usort;

/**
 * Class ContentReplacer
 *
 * Replaces pieces of text in a string, byte for byte. Every old text is looked up in the original
 * string, so the order of the replacements does not matter and one cannot affect another. Either
 * all replacements apply or none does.
 *
 * @since 1.1.3
 */
class ContentReplacer {

	/**
	 * Returns the content with all replacements applied.
	 *
	 * @since  1.1.3
	 * @param  string $content      The content.
	 * @param  array  $replacements List of arrays with old_text and new_text.
	 * @return string|WP_Error The new content, or what is wrong with the replacements.
	 */
	public function replace( string $content, array $replacements ): string|WP_Error {
		$matches  = array();
		$problems = array();

		foreach ( array_values( $replacements ) as $index => $replacement ) {
			$match = $this->find_match( $content, $replacement, $index + 1 );

			if ( is_string( $match ) ) {
				$problems[] = $match;
				continue;
			}

			$matches[] = $match;
		}

		usort( $matches, static fn ( array $a, array $b ): int => $a['offset'] <=> $b['offset'] );

		$problems = array_merge( $problems, $this->find_overlaps( $matches ) );
		if ( array() !== $problems ) {
			return $this->build_error( $problems );
		}

		return $this->apply_matches( $content, $matches );
	}

	/**
	 * Returns where the old text of a replacement is, or why it cannot be used.
	 *
	 * @since  1.1.3
	 * @param  string $content     The content.
	 * @param  mixed  $replacement The replacement.
	 * @param  int    $number      The number of the replacement, counted from 1.
	 * @return array|string Number, offset, length and new text; or the problem.
	 */
	private function find_match( string $content, mixed $replacement, int $number ): array|string {
		$old_text = $replacement['old_text'] ?? '';
		$new_text = $replacement['new_text'] ?? null;

		if ( ! is_string( $old_text ) || '' === $old_text || ! is_string( $new_text ) ) {
			/* translators: %d: the number of the replacement. */
			return sprintf( __( 'Replacement %d needs an old_text and a new_text.', 'core-functionality' ), $number );
		}

		$offset = strpos( $content, $old_text );
		if ( false === $offset ) {
			/* translators: %d: the number of the replacement. */
			return sprintf( __( 'Replacement %d: the old text was not found. Copy it exactly from the raw content of get-content.', 'core-functionality' ), $number );
		}

		// Searching from the next byte also finds a second occurrence that overlaps the first.
		if ( false !== strpos( $content, $old_text, $offset + 1 ) ) {
			/* translators: %d: the number of the replacement. */
			return sprintf( __( 'Replacement %d: the old text occurs more than once. Add surrounding text to make it unique.', 'core-functionality' ), $number );
		}

		return array(
			'number'   => $number,
			'offset'   => $offset,
			'length'   => strlen( $old_text ),
			'new_text' => $new_text,
		);
	}

	/**
	 * Returns a problem for each pair of neighbouring matches that overlap.
	 *
	 * @since  1.1.3
	 * @param  array $matches The matches, sorted by offset.
	 * @return string[] The problems.
	 */
	private function find_overlaps( array $matches ): array {
		$problems = array();
		$previous = null;

		foreach ( $matches as $match ) {
			if ( null !== $previous && $match['offset'] < $previous['offset'] + $previous['length'] ) {
				$problems[] = sprintf(
					/* translators: 1: the number of a replacement, 2: the number of another replacement. */
					__( 'Replacements %1$d and %2$d overlap. Combine them into one.', 'core-functionality' ),
					$previous['number'],
					$match['number']
				);
			}

			$previous = $match;
		}

		return $problems;
	}

	/**
	 * Returns the content with the matches filled in, from back to front so offsets stay valid.
	 *
	 * @since  1.1.3
	 * @param  string $content The content.
	 * @param  array  $matches The matches, sorted by offset, without overlaps.
	 * @return string The new content.
	 */
	private function apply_matches( string $content, array $matches ): string {
		foreach ( array_reverse( $matches ) as $match ) {
			$content = substr_replace( $content, $match['new_text'], $match['offset'], $match['length'] );
		}

		return $content;
	}

	/**
	 * Returns the error that lists all problems, so they can be solved in one go.
	 *
	 * @since  1.1.3
	 * @param  string[] $problems The problems.
	 * @return WP_Error The error.
	 */
	private function build_error( array $problems ): WP_Error {
		return new WP_Error(
			'cf_ai_replace',
			/* translators: %s: what is wrong with the replacements. */
			sprintf( __( 'Nothing was changed. %s', 'core-functionality' ), implode( ' ', $problems ) )
		);
	}
}
