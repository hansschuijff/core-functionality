<?php
/**
 * Own Duplication Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\DuplicatePost\Features\OwnDuplication
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\DuplicatePost\Features\OwnDuplication;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;
use WP_Post;

use function __;
use function add_action;
use function add_filter;
use function apply_filters;
use function in_array;
use function is_array;

/**
 * Class OwnDuplication
 *
 * Hides the links and bulk actions of Yoast Duplicate Post for events, tickets, courses and
 * lessons. Their plugins duplicate them with their own functions, which take care of what belongs
 * to them (new ticket products for a copied event, the lessons of a copied course); a plain copy of
 * the post and its meta would share those and mix up two items. One duplicate link per item also
 * avoids confusion. The post types stay enabled in Duplicate Post, so code (such as the AI Access
 * rewrite copies) can still use it. Posts and pages keep their links.
 *
 * @since 1.0.0
 */
class OwnDuplication extends AbstractFeature {

	/**
	 * The post types with their own duplication, once read.
	 *
	 * @var string[]|null
	 */
	private ?array $post_types = null;

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'duplicate_post_own_duplication',
			name:        __( 'Own duplication', 'core-functionality' ),
			description: __( 'Duplicate Post shows no links for the content types whose own plugin duplicates them (by default events, tickets, courses and lessons), chosen on a settings tab.', 'core-functionality' ),
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( OwnDuplicationSettings::class );
			}
		);

		add_filter( 'duplicate_post_show_link', array( $this, 'filter_show_link' ), 10, 2 );

		// Duplicate Post adds its bulk actions per enabled post type on admin_init (priority 10).
		foreach ( $this->get_excluded_post_types() as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'remove_bulk_actions' ), 20 );
		}
	}

	/**
	 * Hides the links (list rows, admin toolbar, editor) for the post types with their own duplication.
	 *
	 * @since  1.0.0
	 * @param  mixed $show Whether Duplicate Post shows its links for the post.
	 * @param  mixed $post The post.
	 * @return mixed False for those post types, otherwise the value as it was.
	 */
	public function filter_show_link( mixed $show, mixed $post ): mixed {
		if ( $post instanceof WP_Post && in_array( $post->post_type, $this->get_excluded_post_types(), true ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Removes Duplicate Post's bulk actions from a list of one of those post types.
	 *
	 * @since  1.0.0
	 * @param  mixed $actions The bulk actions.
	 * @return mixed The bulk actions.
	 */
	public function remove_bulk_actions( mixed $actions ): mixed {
		if ( is_array( $actions ) ) {
			unset( $actions['duplicate_post_bulk_clone'], $actions['duplicate_post_bulk_rewrite_republish'] );
		}

		return $actions;
	}

	/**
	 * Returns the post types that are duplicated by their own plugin.
	 *
	 * @since  1.0.0
	 * @return string[] The post types.
	 */
	private function get_excluded_post_types(): array {
		if ( null !== $this->post_types ) {
			return $this->post_types;
		}

		$chosen = OwnDuplicationSettings::get_values( $this->plugin )['own_duplication_post_types'];

		/**
		 * Filters the post types for which Duplicate Post shows no links, because their own plugin
		 * duplicates them. Starts from the choices of the Duplicate Post settings tab.
		 *
		 * @since 1.0.0
		 * @param string[] $post_types The post types.
		 */
		$post_types = apply_filters( 'core-functionality/duplicate-post-own-duplication-types', $chosen );

		$this->post_types = is_array( $post_types ) ? $post_types : $chosen;

		return $this->post_types;
	}
}
