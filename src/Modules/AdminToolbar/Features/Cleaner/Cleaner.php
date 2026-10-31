<?php
/**
 * Cleans the admin toolbar by moving less used items to an overflow subnav.
 * It decides which nodes to move based on a configuration matrix, defaulting
 * to an overflow state unless explicitly chosen to be maintained on the main bar.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AdminToolbar\Features\Cleaner
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Cleaner;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;
use WP_Admin_Bar;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Cleaner\CleanerSettings;

use function __;
use function is_admin_bar_showing;
use function is_array;
use function array_keys;
use function is_object;
use function add_action;

/**
 * Class Cleaner
 *
 * Implements the operational runtime hook lifecycle filtering the active admin bar
 * nodes using a negative-selection matrix blueprint.
 *
 * @since 1.0.0
 */
class Cleaner extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'admin_toolbar_cleaner',
			name:          __( 'Clean up', 'core-functionality' ),
			description:   __( 'Moves unwanted Admin Toolbar shortcuts to an overflow submenu, based on a negative-selection user matrix.', 'core-functionality' ),
		);
	}

	/**
	 * Launches the operational execution lifecycle for this micro-feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function launch(): void {

		// Register the settings page section.
		$this->register_settings_section();

		// Hook the main cleaner method.
		if ( is_admin_bar_showing() ) {
			add_action( 'wp_before_admin_bar_render', array( $this, 'move_nodes_to_overflow_menu' ), 200 );
		}
	}

	/**
	 * Register the settings section of this feature.
	 *
	 * @return void
	 */
	private function register_settings_section() {
		// Securely register the configuration panel into the host page factory autonomously.
		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( CleanerSettings::class );
			}
		);
	}

	/**
	 * Interrogates single-source options vault matrix mappings to restructure the WordPress admin bar nodes.
	 *
	 * Intercepts the live global WP_Admin_Bar nodes, logs discovered top-levels, and forces unapproved into overflow.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function move_nodes_to_overflow_menu(): void {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		// Fetch all nodes in the current admin bar.
		$all_nodes = $wp_admin_bar->get_nodes();
		if ( empty( $all_nodes ) || ! is_array( $all_nodes ) ) {
			return;
		}

		$all_movable_nodes = $this->get_movable_nodes( $all_nodes );
		if ( ! empty( $all_movable_nodes ) ) {
			$this->save_movable_nodes( $all_movable_nodes );
		}

		if ( $this->is_cleaner_settings_page() ) {
			// don't clean on the cleaner settings page, so all top level nodes stay visible.
			return;
		}

		// Pull active matrix visibility configurations out of the managed Settings service.
		$keep_in_place_nodes = $this->plugin->settings->get( CleanerSettings::get_settings_key(), $this->module::get_id() );
		if ( ! is_array( $keep_in_place_nodes ) ) {
			$keep_in_place_nodes = array();
		}

		// Scan through the top-level movable nodes to see if we need an overflow menu.
		$need_to_move = false;
		// get_movable_nodes() returns arrays keyed by node id, not WP_Admin_Bar node objects.
		foreach ( array_keys( $all_movable_nodes ) as $node_id ) {
			$keep_in_place = isset( $keep_in_place_nodes[ $node_id ]['main'] )
				? (bool) $keep_in_place_nodes[ $node_id ]['main']
				: false;

			if ( ! $keep_in_place ) {
				// There is at least one node that needs to be moved.
				$need_to_move = true;
				break;
			}
		}

		// Create the overflow menu node.
		if ( $need_to_move && ! $wp_admin_bar->get_node( self::get_overflow_node_id() ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => self::get_overflow_node_id(),
					'title' => __( 'More', 'core-functionality' ),
					'href'  => '#',
				)
			);
		}

		// Make the movable nodes children of the overflow menu node.
		foreach ( $all_nodes as $node ) {
			// Skip lower level nodes and protected nodes.
			if ( ! empty( $node->parent ) || in_array( $node->id, self::get_protected_nodes(), true ) ) {
				continue;
			}

			$must_keep_node = isset( $keep_in_place_nodes[ $node->id ]['main'] )
				? (bool) $keep_in_place_nodes[ $node->id ]['main']
				: false;

			if ( ! $must_keep_node ) {
				$node_args = array(
					'id'     => $node->id,
					'title'  => $node->title,
					'parent' => self::get_overflow_node_id(),
					'href'   => $node->href,
					'meta'   => $node->meta,
				);
				// Adding a node that allready exists just moves it to the new parent.
				$wp_admin_bar->add_node( $node_args );
			}
		}
	}

	/**
	 * Adds newly found top-level toolbar nodes to the stored list, so the settings tab can offer them.
	 *
	 * @since  1.0.0
	 * @param  array $all_movable_nodes The movable nodes found on this request, keyed by node id.
	 * @return void
	 */
	private function save_movable_nodes( array $all_movable_nodes ) {
		// Merge the newly found nodes with the already saved ones, to make sure no nodes are forgotten.
		$saved_nodes = $this->plugin->settings->get( self::get_movable_nodes_key(), $this->module::get_id() );

		$saved_nodes  = is_array( $saved_nodes ) ? $saved_nodes : array();
		$merged_nodes = array_merge( $saved_nodes, $all_movable_nodes );

		$this->plugin->settings->save( self::get_movable_nodes_key(), $merged_nodes );
	}

	/**
	 * Checks whether the current admin page is the Toolbar Cleaner settings tab.
	 *
	 * @since  1.0.0
	 * @return bool True on that tab, false otherwise.
	 */
	private function is_cleaner_settings_page(): bool {
		global $plugin_page;
		return is_admin()
			&& isset( $plugin_page ) && AdminSettingsPage::SLUG === $plugin_page
			&& isset( $_GET['section'] ) && CleanerSettings::get_id() === $_GET['section'];
	}

	/**
	 * Returns the top-level toolbar nodes that may be moved into the overflow menu.
	 *
	 * @since  1.0.0
	 * @param  array $nodes All nodes of the admin toolbar.
	 * @return array<string, array{id: string, label: string}> Node id => id and label.
	 */
	private function get_movable_nodes( array $nodes ) {
		$movable_top_level_nodes = array();
		foreach ( $nodes as $node ) {
			if ( empty( $node->parent ) && ! in_array( $node->id, self::get_protected_nodes(), true ) ) {

				$movable_top_level_nodes[ $node->id ] = array(
					'id'    => $node->id,
					'label' => ! empty( $node->title ) ? strip_tags( (string) $node->title ) : $node->id,
				);
			}
		}
		return $movable_top_level_nodes;
	}

	/**
	 * Returns the settings key of the stored movable nodes.
	 *
	 * @since  1.0.0
	 * @return string The settings key.
	 */
	public static function get_movable_nodes_key(): string {
		return 'admin-toolbar-cleaner-movable-nodes';
	}

	/**
	 * Returns the id of the "More" overflow node.
	 *
	 * @since  1.0.0
	 * @return string The node id.
	 */
	public static function get_overflow_node_id(): string {
		return 'cf_more_admin_bar_menus';
	}

	/**
	 * Returns the ids of the toolbar nodes that always stay in place.
	 *
	 * @since  1.0.0
	 * @return string[] The node ids.
	 */
	public static function get_protected_nodes(): array {
		return array(
			'wp-logo',
			'site-name',
			'updates',
			'comments',
			'new-content',
			'my-account',
			'menu-toggle',               // The hamburger menu icon for small screens.
			'top-secondary',             // The account panel on the right side.
			self::get_overflow_node_id(), // Our overflow (more) menu node.
		);
	}
}
