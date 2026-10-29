<?php
/**
 * Debug Toolkit Application Guard and Dashboard Component.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Plugins
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class DebugToolkit
 *
 * Evaluates the runtime tracking status of the Debug Toolkit plugin.
 * Encapsulates its own admin toolbar node component layout specifications.
 *
 * @since 1.0.0
 */
class DebugToolkit extends AbstractPlugin {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'debug-toolkit';
	}

	/**
	 * Declares the specific technical proof criteria required for Debug Toolkit verification.
	 *
	 * Defaults to an empty requirement object if the basic is_plugin_active database check is sufficient.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The structured proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'KnowTheCode\\DebugToolkit\\_get_plugin_root_dir' );
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this plugin can offer.
	 *
	 * Maps your custom criteria (including the specific CSS class) into a type-safe DTO.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of plugin toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-debug-toolkit',
				title:   __( '🛠️ Debug Toolkit', 'core-functionality' ),
				href:    admin_url( 'plugins.php?plugin_status=active' ),
				label:   __( 'Debug Toolkit Diagnostics', 'core-functionality' ),
				meta:    array( 'class' => 'dwp-debug-bar-item' ), // Adds a CSS class for styling.
				orientation: Orientation::ADMIN,
			),
		);
	}
}
