<?php
/**
 * Yoast SEO Fields for the AI Access abilities.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\SeoFields
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\SeoFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\FieldHandlerInterface;
use Override;
use WP_Error;
use WP_Post;

use function delete_post_meta;
use function get_post_meta;
use function sanitize_text_field;
use function update_post_meta;

/**
 * Class YoastSeoFields
 *
 * The SEO title and meta description that Yoast SEO keeps per post. Empty means: Yoast's own
 * template applies.
 *
 * @since 1.0.0
 */
class YoastSeoFields implements FieldHandlerInterface {

	/**
	 * The fields and the meta keys Yoast SEO stores them under.
	 *
	 * @var array<string, string>
	 */
	private const META_KEYS = array(
		'title'       => '_yoast_wpseo_title',
		'description' => '_yoast_wpseo_metadesc',
	);

	/**
	 * Returns the key of this group under "fields".
	 *
	 * @since  1.0.0
	 * @return string The id.
	 */
	#[Override]
	public function get_id(): string {
		return 'seo';
	}

	/**
	 * Returns whether this group belongs to a post type: every content type has these fields.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True.
	 */
	#[Override]
	public function applies_to( string $post_type ): bool {
		return true;
	}

	/**
	 * Returns the JSON schema of the values this group accepts.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	#[Override]
	public function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'description'          => 'Yoast SEO. An empty string removes the value, so the site\'s template applies.',
			'additionalProperties' => false,
			'properties'           => array(
				'title'       => array(
					'type'        => 'string',
					'description' => 'SEO title; may use Yoast variables such as %%title%% %%sep%% %%sitename%%.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Meta description, about 120 to 155 characters.',
				),
			),
		);
	}

	/**
	 * Returns the current values of a post.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return array The values.
	 */
	#[Override]
	public function read( WP_Post $post ): array {
		$values = array();
		foreach ( self::META_KEYS as $field => $meta_key ) {
			$values[ $field ] = (string) get_post_meta( $post->ID, $meta_key, true );
		}

		return $values;
	}

	/**
	 * Saves the given values on a post.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post   The post, already saved.
	 * @param  array   $values The values from the input.
	 * @return true|WP_Error True.
	 */
	#[Override]
	public function write( WP_Post $post, array $values ): true|WP_Error {
		foreach ( self::META_KEYS as $field => $meta_key ) {
			if ( ! isset( $values[ $field ] ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $values[ $field ] );
			if ( '' === $value ) {
				delete_post_meta( $post->ID, $meta_key );
			} else {
				update_post_meta( $post->ID, $meta_key, $value );
			}
		}

		return true;
	}
}
