<?php
/**
 * Admin Menu Architect Feature.
 *
 * @package DeWittePrins\CoreFunctionality\Modules\AdminMenu\Features\AdminMenuArchitect
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Modules\AdminMenu\Features\AdminMenuArchitect;

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;
use DeWittePrins\CoreFunctionality\Modules\AdminMenu\DTO\AdminMenuConfigNode;

use function __;
use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function remove_menu_page;
use function current_user_can;

/**
 * Class AdminMenuArchitect
 *
 * Compiles, orders, filters, and cleans up the WordPress admin sidebar navigation.
 */
class AdminMenuArchitect extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'admin_menu_architect',
			name:          __( 'Arrange', 'core-functionality' ),
			description:   __( 'Architects, structures and cleans up the admin side navigation.', 'core-functionality' ),
		);
	}

	/**
	 * Launches the operational execution lifecycle for this feature.
	 *
	 * @return void
	 */
	public function launch(): void {
		// Hook for adding and managing custom menu structures.
		add_action( 'admin_menu', array( $this, 'compile_and_register_menus' ), 100 );

		// Hook for removing unwanted native/core menus (runs late to catch everything cleanly).
		add_action( 'admin_menu', array( $this, 'remove_blacklisted_menus' ), 9999 );
	}

	/**
	 * Compiles configuration and registers allowed menu items.
	 *
	 * @return void
	 */
	public function compile_and_register_menus(): void {
		return;
		$raw_items = (array) $this->plugin->settings->get( 'menu-add', $this->module->get_id() );
		$defaults  = (array) $this->plugin->settings->get( 'menu-add-defaults', $this->module->get_id() );

		if ( empty( $raw_items ) ) {
			return;
		}

		$config_nodes = array();
		foreach ( $raw_items as $item_array ) {
			$config_nodes[] = AdminMenuConfigNode::from_array( $item_array, $defaults );
		}

		/**
		 * Filters the compiled admin menu configuration nodes before registration.
		 *
		 * @param AdminMenuConfigNode[] $config_nodes Array of config node DTOs.
		 */
		$filtered_nodes = apply_filters( 'core-functionality/admin-menu-config-nodes', $config_nodes );

		foreach ( $filtered_nodes as $node ) {
			if ( ! $node instanceof AdminMenuConfigNode || ! $node->is_enabled ) {
				continue; // Here we successfully replicate the "remove/hide" logic by skipping disabled blocks.
			}

			$block = $node->block;

			if ( empty( $block->page_title ) || empty( $block->menu_title ) || ! current_user_can( $block->capability ) ) {
				continue;
			}

			if ( $block->parent_slug ) {
				add_submenu_page(
					$block->parent_slug,
					$block->page_title,
					$block->menu_title,
					$block->capability,
					$block->menu_slug,
					$block->callback,
					$block->menu_position
				);
				continue;
			}

			add_menu_page(
				$block->page_title,
				$block->menu_title,
				$block->capability,
				$block->menu_slug,
				$block->callback,
				$block->icon,
				$block->menu_position
			);
		}
	}

	/**
	 * Safely removes core WordPress menu nodes based on structural slugs.
	 *
	 * Replaces the unsafe title-matching unset($menu) logic with robust slug removal.
	 *
	 * @return void
	 */
	public function remove_blacklisted_menus(): void {
		// Read the slugs to remove (e.g., array('edit.php', 'upload.php', 'edit-comments.php')).
		$slugs_to_remove = (array) $this->plugin->settings->get( 'menu-remove', $this->module->get_id() );

		if ( empty( $slugs_to_remove ) ) {
			return;
		}

		/**
		 * Filters the admin menu slugs targeted for removal.
		 *
		 * @param string[] $slugs_to_remove Array of WordPress admin menu slug strings.
		 */
		$slugs_to_remove = apply_filters( 'core-functionality/admin-menu-removals', $slugs_to_remove );

		foreach ( $slugs_to_remove as $slug ) {
			if ( is_string( $slug ) && ! empty( $slug ) ) {
				remove_menu_page( $slug );
			}
		}
	}
}
