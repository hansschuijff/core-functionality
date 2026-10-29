<?php
/**
 * Content Types available to an AI assistant.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\ContentTypeProfileInterface;
use DeWittePrins\CoreFunctionality\Plugin;

use function apply_filters;
use function array_filter;
use function array_keys;
use function is_array;
use function post_type_exists;

/**
 * Class ContentTypes
 *
 * Collects the content type profiles and applies the choices of the AI Access settings tab:
 * which types an AI assistant may use, and for which a Rewrite & Republish copy is allowed.
 *
 * @since 1.0.0
 */
class ContentTypes {

	/**
	 * The profiles, keyed by post type, once collected.
	 *
	 * @var ContentTypeProfileInterface[]|null
	 */
	private ?array $profiles = null;

	/**
	 * The saved choices per post type, once read.
	 *
	 * @var array<string, array{enabled?: bool, rewrite?: bool}>|null
	 */
	private ?array $choices = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns every known profile of a registered post type, whether switched on or not.
	 *
	 * @since  1.0.0
	 * @return ContentTypeProfileInterface[] The profiles, keyed by post type.
	 */
	public function get_all(): array {
		if ( null !== $this->profiles ) {
			return $this->profiles;
		}

		$defaults = array(
			'post' => new ContentTypeProfile( 'post', taxonomies: array( 'category', 'post_tag' ), allows_rewrite: true ),
			'page' => new ContentTypeProfile( 'page', allows_rewrite: true ),
		);

		/**
		 * Filters the content types an AI assistant may read and write.
		 *
		 * Add a profile to make a post type available, or replace one to change how it is handled.
		 * The settings tab can still switch each type off.
		 *
		 * @since 1.0.0
		 * @param ContentTypeProfileInterface[] $profiles The profiles, keyed by post type.
		 */
		$profiles = apply_filters( 'core-functionality/ai-access-content-types', $defaults );
		$profiles = is_array( $profiles ) ? $profiles : $defaults;

		$this->profiles = array();
		foreach ( $profiles as $profile ) {
			if ( $profile instanceof ContentTypeProfileInterface && post_type_exists( $profile->get_post_type() ) ) {
				$this->profiles[ $profile->get_post_type() ] = $profile;
			}
		}

		return $this->profiles;
	}

	/**
	 * Returns the profiles that are switched on.
	 *
	 * @since  1.0.0
	 * @return ContentTypeProfileInterface[] The profiles, keyed by post type.
	 */
	public function get_enabled(): array {
		return array_filter(
			$this->get_all(),
			fn ( ContentTypeProfileInterface $profile ) => $this->get_choice( $profile, 'enabled' )
		);
	}

	/**
	 * Returns the post types that are switched on.
	 *
	 * @since  1.0.0
	 * @return string[] Post types.
	 */
	public function get_enabled_post_types(): array {
		return array_keys( $this->get_enabled() );
	}

	/**
	 * Returns the profile of a post type when that type is switched on.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return ContentTypeProfileInterface|null The profile, or null.
	 */
	public function get( string $post_type ): ?ContentTypeProfileInterface {
		return $this->get_enabled()[ $post_type ] ?? null;
	}

	/**
	 * Returns whether a Rewrite & Republish copy is allowed for a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True when the type is switched on and its copies are allowed.
	 */
	public function allows_rewrite( string $post_type ): bool {
		$profile = $this->get( $post_type );

		return null !== $profile && $this->get_choice( $profile, 'rewrite' );
	}

	/**
	 * Returns one choice of the settings tab for a profile, or its default when none was saved.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  string                      $choice  'enabled' or 'rewrite'.
	 * @return bool The choice.
	 */
	public function get_choice( ContentTypeProfileInterface $profile, string $choice ): bool {
		$this->choices ??= ContentEditingSettings::get_values( $this->plugin )['types'];

		$saved = $this->choices[ $profile->get_post_type() ][ $choice ] ?? null;
		if ( null !== $saved ) {
			return (bool) $saved;
		}

		return 'rewrite' === $choice ? $profile->allows_rewrite_by_default() : true;
	}
}
