<?php
/**
 * WP Server Stats Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class WpServerStats
 *
 * Reports whether WP Server Stats is active, and supplies the links of its admin menu.
 *
 * @since 1.0.0
 */
class WpServerStats extends AbstractPlugin {

	/**
	 * Returns the environment id of WP Server Stats.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'wp-server-stats';
	}

	/**
	 * Returns what must exist to prove WP Server Stats has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Its class only exists on admin pages; this constant is defined wherever the plugin loads.
		return new ProofRequirement( constants: 'WP_SERVER_STATS_BASE' );
	}

	/**
	 * Returns the admin toolbar links WP Server Stats offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then the plugin's own admin menu.
		return array(
			new ToolbarShortcut(
				id:          'cf-wp-server-stats',
				title:       'WP Server Stats',
				href:        admin_url( 'admin.php?page=wp_server_stats' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WP Server Stats', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-wp-server-stats-settings',
				title:       __( 'General Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wp_server_stats' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'WP Server Stats General Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-wp-server-stats-php-info',
				title:       __( 'PHP Information', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wpss_php_info' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'PHP Information', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-wp-server-stats-sql-info',
				title:       __( 'Database Information', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=wpss_sql_info' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Database Information', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
