<?php
/**
 * Admin Toolbar Node ID Visualizer Developer Feature.
 *
 * A helper function that adds a toplevel menu to the admin toolbar showing
 * all current node-id's in the admin toolbar.
 *
 * @package DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Visualizer
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Visualizer;

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;

use function __;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Visualizer
 *
 * Maps active runtime Node ID keys for development troubleshooting.
 *
 * @since 1.0.0
 */
class Visualizer extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'admin_toolbar_visualizer',
			name:          __( 'Admin Toolbar Visualizer Feature', 'core-functionality' ),
			description:   __( 'The Admin Toolbar Visualizer Feature Maps active runtime Node ID keys for development troubleshooting.', 'core-functionality' ),
			status:        Deployment::STAGING,
		);
	}

	/**
	 * Launches the operational execution lifecycle for this developer tool.
	 *
	 * @return void
	 */
	public function launch(): void {
		add_action( 'wp_before_admin_bar_render', array( $this, 'render_node_inspector' ), 300 );
	}

	/**
	 * Inspects the global admin bar registry and maps nodes into a readable dropdown tree.
	 *
	 * @global \WP_Admin_Bar $wp_admin_bar Global WordPress admin bar object.
	 * @return void
	 */
	public function render_node_inspector(): void {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) || ! current_user_can( 'administrator' ) ) {
			return;
		}

		$all_nodes = $wp_admin_bar->get_nodes();

		if ( empty( $all_nodes ) ) {
			return;
		}

		$root_inspector_id = 'dwp_node_inspector_root';

		$wp_admin_bar->add_node(
			array(
				'id'    => $root_inspector_id,
				'title' => __( 'Node ID\'s', 'core-functionality' ),
				'href'  => '#',
			)
		);

		foreach ( $all_nodes as $node ) {
			if ( ! empty( $node->parent ) ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'node_id_' . $node->id,
						'title'  => $node->id,
						'parent' => $root_inspector_id,
					)
				);
			}
		}

		foreach ( $all_nodes as $node ) {
			$args = array(
				'id'    => 'node_id_' . $node->id,
				'title' => $node->id,
			);

			if ( ! empty( $node->parent ) ) {
				$args['parent'] = 'node_id_' . $node->parent;
			} else {
				$args['parent'] = $root_inspector_id;
			}

			$wp_admin_bar->add_node( $args );
		}
	}
}
