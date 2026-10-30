<?php
/**
 * Site Kit by Google Verification Guard.
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
 * Class SiteKit
 *
 * Represents Site Kit by Google in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class SiteKit extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'site-kit';
	}

	/**
	 * Returns the name the plugin is known by (its header says "Site Kit by Google").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Site Kit';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'GOOGLESITEKIT_VERSION' );
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
				id:          'cf-site-kit',
				title:       __( 'Site Kit', 'core-functionality' ),
				path:        'admin.php?page=googlesitekit-dashboard',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-site-kit-settings',
				title:       __( 'Site Kit Settings', 'core-functionality' ),
				path:        'admin.php?page=googlesitekit-settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
