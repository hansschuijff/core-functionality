<?php
/**
 * Unsafe Markup Check of the AI Access abilities.
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

use WP_HTML_Tag_Processor;

use function array_keys;
use function array_map;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function strtolower;
use function wp_allowed_protocols;
use function wp_kses_allowed_html;
use function wp_kses_bad_protocol;

/**
 * Class UnsafeMarkupCheck
 *
 * Finds markup that wp_kses_post() would not allow and that a change adds to the content. Running
 * wp_kses_post() over the whole content would remove what is already there, such as inline SVG;
 * running it over only the new text misses a change that makes a fragment unsafe in its place,
 * such as turning class="x" into onclick="x". Comparing before and after covers both.
 *
 * @since 1.1.3
 */
class UnsafeMarkupCheck {

	/**
	 * The attributes that hold an address.
	 *
	 * @var string[]
	 */
	private const URL_ATTRIBUTES = array( 'action', 'background', 'cite', 'data', 'formaction', 'href', 'longdesc', 'poster', 'src', 'srcdoc', 'xlink:href' );

	/**
	 * The tags whose text runs as code.
	 *
	 * @var string[]
	 */
	private const CODE_TAGS = array( 'script', 'style' );

	/**
	 * Returns the unsafe markup the new content has and the old content has not.
	 *
	 * @since  1.1.3
	 * @param  string $old_content The current content.
	 * @param  string $new_content The new content.
	 * @return string[] A description of what is added; empty when nothing is.
	 */
	public function find_added( string $old_content, string $new_content ): array {
		$old_counts = $this->count_unsafe_markup( $old_content );
		$added      = array();

		foreach ( $this->count_unsafe_markup( $new_content ) as $description => $count ) {
			if ( $count > ( $old_counts[ $description ] ?? 0 ) ) {
				$added[] = (string) $description;
			}
		}

		return $added;
	}

	/**
	 * Returns all unsafe markup in the html: what wp_kses_post() would remove.
	 *
	 * @since  1.1.3
	 * @param  string $html The html.
	 * @return string[] A description of the markup; empty when there is none.
	 */
	public function find_all( string $html ): array {
		return array_map( 'strval', array_keys( $this->count_unsafe_markup( $html ) ) );
	}

	/**
	 * Returns how often each piece of unsafe markup occurs.
	 *
	 * @since  1.1.3
	 * @param  string $html The html.
	 * @return array Description => number of times.
	 */
	private function count_unsafe_markup( string $html ): array {
		$allowed   = wp_kses_allowed_html( 'post' );
		$processor = new WP_HTML_Tag_Processor( $html );
		$counts    = array();

		while ( $processor->next_tag() ) {
			foreach ( $this->describe_unsafe_markup_of_tag( $processor, $allowed ) as $description ) {
				$counts[ $description ] = ( $counts[ $description ] ?? 0 ) + 1;
			}
		}

		// The processor stops at a tag it cannot finish, such as a script without an end tag, and
		// sees nothing of what follows. So that counts as unsafe in itself.
		if ( $processor->paused_at_incomplete_token() ) {
			$counts['incomplete markup'] = 1;
		}

		return $counts;
	}

	/**
	 * Returns a description of everything unsafe in the current tag.
	 *
	 * @since  1.1.3
	 * @param  WP_HTML_Tag_Processor $processor The processor, on a tag.
	 * @param  array                 $allowed   The tags and attributes wp_kses_post() allows.
	 * @return string[] The descriptions.
	 */
	private function describe_unsafe_markup_of_tag( WP_HTML_Tag_Processor $processor, array $allowed ): array {
		$tag          = strtolower( (string) $processor->get_tag() );
		$descriptions = array();

		if ( ! isset( $allowed[ $tag ] ) ) {
			$descriptions[] = $this->describe_tag( $processor, $tag );
		}

		foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
			$value = $processor->get_attribute( $name );
			$value = is_string( $value ) ? $value : '';

			$description = $this->describe_unsafe_attribute( $tag, $name, $value, $allowed );
			if ( null !== $description ) {
				$descriptions[] = $description;
			}
		}

		return $descriptions;
	}

	/**
	 * Returns the description of a tag that is not allowed; with its text when that runs as code,
	 * so a change of that code counts as something new.
	 *
	 * @since  1.1.3
	 * @param  WP_HTML_Tag_Processor $processor The processor, on the tag.
	 * @param  string                $tag       The tag name in lower case.
	 * @return string The description.
	 */
	private function describe_tag( WP_HTML_Tag_Processor $processor, string $tag ): string {
		if ( in_array( $tag, self::CODE_TAGS, true ) ) {
			return "<{$tag}> = " . $processor->get_modifiable_text();
		}

		return "<{$tag}>";
	}

	/**
	 * Returns the description of an attribute if it is unsafe.
	 *
	 * An attribute wp_kses_post() does not allow always counts, but its value only when that can
	 * run code or load something: changing a color in existing SVG is fine, changing a handler is not.
	 *
	 * @since  1.1.3
	 * @param  string $tag     The tag name in lower case.
	 * @param  string $name    The attribute name in lower case.
	 * @param  string $value   The attribute value.
	 * @param  array  $allowed The tags and attributes wp_kses_post() allows.
	 * @return string|null The description, or null when the attribute is safe.
	 */
	private function describe_unsafe_attribute( string $tag, string $name, string $value, array $allowed ): ?string {
		$is_url     = in_array( $name, self::URL_ATTRIBUTES, true );
		$with_value = "{$tag}[{$name}] = {$value}";

		if ( $this->is_allowed_attribute( $tag, $name, $allowed ) ) {
			return $is_url && $this->has_bad_protocol( $value ) ? $with_value : null;
		}

		return $is_url || str_starts_with( $name, 'on' ) ? $with_value : "{$tag}[{$name}]";
	}

	/**
	 * Returns whether wp_kses_post() allows an attribute on a tag.
	 *
	 * @since  1.1.3
	 * @param  string $tag     The tag name in lower case.
	 * @param  string $name    The attribute name in lower case.
	 * @param  array  $allowed The tags and attributes wp_kses_post() allows.
	 * @return bool True when it is allowed.
	 */
	private function is_allowed_attribute( string $tag, string $name, array $allowed ): bool {
		if ( ! isset( $allowed[ $tag ] ) || ! is_array( $allowed[ $tag ] ) ) {
			return false;
		}

		$attributes = array_keys( $allowed[ $tag ] );

		return in_array( $name, $attributes, true )
			|| ( str_starts_with( $name, 'data-' ) && in_array( 'data-*', $attributes, true ) );
	}

	/**
	 * Returns whether an address uses a protocol WordPress does not allow, such as javascript:.
	 *
	 * @since  1.1.3
	 * @param  string $value The address.
	 * @return bool True when it does.
	 */
	private function has_bad_protocol( string $value ): bool {
		return wp_kses_bad_protocol( $value, wp_allowed_protocols() ) !== $value;
	}
}
