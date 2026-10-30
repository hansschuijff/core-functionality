<?php
/**
 * WP Crontrol Verification Guard.
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
 * Class WpCrontrol
 *
 * Represents WP Crontrol in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class WpCrontrol extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'wp-crontrol';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'Crontrol\\WP_CRONTROL_VERSION' );
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
				id:          'cf-wp-crontrol',
				title:       __( 'Cron Events', 'core-functionality' ),
				path:        'tools.php?page=wp-crontrol',
				label:       __( 'WP Crontrol', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-wp-crontrol-logs',
				title:       __( 'Cron Logs', 'core-functionality' ),
				path:        'tools.php?page=wp-crontrol-logs',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-wp-crontrol-insights',
				title:       __( 'Cron Insights', 'core-functionality' ),
				path:        'tools.php?page=wp-crontrol-insights',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-wp-crontrol-alerts',
				title:       __( 'Cron Alerts', 'core-functionality' ),
				path:        'tools.php?page=wp-crontrol-alerts',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-wp-crontrol-ai',
				title:       __( 'Cron AI', 'core-functionality' ),
				path:        'tools.php?page=wp-crontrol-ai',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-wp-crontrol-schedules',
				title:       __( 'Cron Schedules', 'core-functionality' ),
				path:        'options-general.php?page=wp-crontrol-schedules',
			),
		);
	}
}
