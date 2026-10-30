<?php
/**
 * TinyPNG Image Optimization Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;

/**
 * Class TinyPng
 *
 * Represents TinyPNG Image Optimization in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class TinyPng extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'tiny-png';
	}

	/**
	 * Returns the name the plugin is known by (its header says "TinyPNG Image Optimization").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'TinyPNG';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'Tiny_Plugin' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			ToolbarShortcut::to_admin_page(
				id:          'cf-tiny-png-bulk',
				title:       __( 'Bulk Image Optimization', 'core-functionality' ),
				path:        'upload.php?page=tiny-bulk-optimization',
				short_title: __( 'Bulk Optimization', 'core-functionality' ),
				label:       __( 'TinyPNG Bulk Optimization', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tiny-png-settings',
				title:       __( 'TinyPNG Settings', 'core-functionality' ),
				path:        'options-general.php?page=tinify',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
