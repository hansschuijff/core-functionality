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
	 * Level: find and read items of the type.
	 *
	 * @var string
	 */
	public const LEVEL_READ = 'read';

	/**
	 * Level: make and change drafts of the type.
	 *
	 * @var string
	 */
	public const LEVEL_WRITE = 'write';

	/**
	 * Level: change published items of the type, in a copy that replaces them once published.
	 *
	 * @var string
	 */
	public const LEVEL_REWRITE = 'rewrite';

	/**
	 * The levels, from low to high; each includes the ones before it.
	 *
	 * @var string[]
	 */
	public const LEVELS = array( self::LEVEL_READ, self::LEVEL_WRITE, self::LEVEL_REWRITE );

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
	 * Returns the profiles an AI assistant may read.
	 *
	 * @since  1.0.0
	 * @return ContentTypeProfileInterface[] The profiles, keyed by post type.
	 */
	public function get_readable(): array {
		return array_filter(
			$this->get_all(),
			fn ( ContentTypeProfileInterface $profile ) => $this->has_level( $profile, self::LEVEL_READ )
		);
	}

	/**
	 * Returns the post types an AI assistant may read.
	 *
	 * @since  1.0.0
	 * @return string[] Post types.
	 */
	public function get_readable_post_types(): array {
		return array_keys( $this->get_readable() );
	}

	/**
	 * Returns the post types in which an AI assistant may make and change drafts.
	 *
	 * @since  1.0.0
	 * @return string[] Post types.
	 */
	public function get_writable_post_types(): array {
		return array_keys(
			array_filter(
				$this->get_all(),
				fn ( ContentTypeProfileInterface $profile ) => $this->has_level( $profile, self::LEVEL_WRITE )
			)
		);
	}

	/**
	 * Returns the profile of a post type an AI assistant may read.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return ContentTypeProfileInterface|null The profile, or null.
	 */
	public function get( string $post_type ): ?ContentTypeProfileInterface {
		return $this->get_readable()[ $post_type ] ?? null;
	}

	/**
	 * Returns whether an AI assistant may make and change drafts of a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True when it may.
	 */
	public function can_write( string $post_type ): bool {
		$profile = $this->get_all()[ $post_type ] ?? null;

		return null !== $profile && $this->has_level( $profile, self::LEVEL_WRITE );
	}

	/**
	 * Returns whether an AI assistant may change published items of a post type in a Rewrite &
	 * Republish copy.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True when copies can be made and that level is switched on for the type.
	 */
	public function allows_rewrite( string $post_type ): bool {
		/**
		 * Filters whether Rewrite & Republish copies can be made at all. The feature that makes
		 * them (with Yoast Duplicate Post) switches this on.
		 *
		 * @since 1.0.0
		 * @param bool $available Whether copies can be made.
		 */
		if ( true !== apply_filters( 'core-functionality/ai-access-rewrite-available', false ) ) {
			return false;
		}

		$profile = $this->get_all()[ $post_type ] ?? null;

		return null !== $profile && $this->has_level( $profile, self::LEVEL_REWRITE );
	}

	/**
	 * Returns whether a level is switched on for a profile. The levels are steps: a level only
	 * counts when every lower level is on too.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  string                      $level   One of the LEVEL_ constants.
	 * @return bool True when that level and every lower one are on.
	 */
	public function has_level( ContentTypeProfileInterface $profile, string $level ): bool {
		foreach ( self::LEVELS as $step ) {
			if ( ! $this->get_choice( $profile, $step ) ) {
				return false;
			}
			if ( $step === $level ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns one choice of the settings tab for a profile, or its default when none was saved:
	 * reading and drafts on, published items as the profile says.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  string                      $level   One of the LEVEL_ constants.
	 * @return bool The choice.
	 */
	public function get_choice( ContentTypeProfileInterface $profile, string $level ): bool {
		$this->choices ??= ContentEditingSettings::get_values( $this->plugin )['types'];

		$saved = $this->choices[ $profile->get_post_type() ][ $level ] ?? null;
		if ( null !== $saved ) {
			return (bool) $saved;
		}

		return self::LEVEL_REWRITE === $level ? $profile->allows_rewrite_by_default() : true;
	}
}
