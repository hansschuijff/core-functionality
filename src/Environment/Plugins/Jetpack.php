<?php
/**
 * Jetpack Verification Guard.
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
 * Class Jetpack
 *
 * Reports whether Jetpack is active and loaded. Supplies its admin links.
 *
 * @since 1.0.0
 */
class Jetpack extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'jetpack';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'JETPACK__VERSION' );
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
				id:          'cf-jetpack',
				title:       __( 'Jetpack', 'core-functionality' ),
				path:        'admin.php?page=my-jetpack',
				label:       __( 'My Jetpack', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-stats',
				title:       __( 'Jetpack Statistics', 'core-functionality' ),
				path:        'admin.php?page=stats',
				short_title: __( 'Statistics', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-sharing',
				title:       __( 'Sharing Settings', 'core-functionality' ),
				path:        'options-general.php?page=sharing',
				short_title: __( 'Sharing', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-activity-log',
				title:       __( 'Activity Log', 'core-functionality' ),
				path:        'admin.php?page=jetpack-activity-log',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-backup',
				title:       __( 'Jetpack Backup', 'core-functionality' ),
				path:        'admin.php?page=jetpack-backup',
				short_title: __( 'Backup', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-forms',
				title:       __( 'Jetpack Forms', 'core-functionality' ),
				path:        'admin.php?page=jetpack-forms-responses-wp-admin',
				short_title: __( 'Forms', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-ai',
				title:       __( 'Jetpack AI', 'core-functionality' ),
				path:        'admin.php?page=jetpack-ai',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-newsletter',
				title:       __( 'Newsletter', 'core-functionality' ),
				path:        'admin.php?page=jetpack-newsletter',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-blaze',
				title:       __( 'Blaze Ads', 'core-functionality' ),
				path:        'admin.php?page=advertising',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-jetpack-settings',
				title:       __( 'Jetpack Settings', 'core-functionality' ),
				path:        'admin.php?page=jetpack-settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
