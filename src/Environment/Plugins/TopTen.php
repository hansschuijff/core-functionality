<?php
/**
 * WebberZone Top 10 Verification Guard.
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
 * Class TopTen
 *
 * Represents WebberZone Top 10 (folder top-10) in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class TopTen extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'top-ten';
	}

	/**
	 * Returns the name the plugin is known by (its header says "WebberZone Top 10").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Top 10';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'TOP_TEN_VERSION' );
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
				id:          'cf-top-ten',
				title:       __( 'Top 10', 'core-functionality' ),
				path:        'admin.php?page=tptn_dashboard',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-top-ten-popular-posts',
				title:       __( 'Popular Posts', 'core-functionality' ),
				path:        'admin.php?page=tptn_popular_posts',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-top-ten-import-export',
				title:       __( 'Top 10 Import/Export', 'core-functionality' ),
				path:        'admin.php?page=tptn_exim_page',
				short_title: __( 'Import/Export', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-top-ten-tools',
				title:       __( 'Top 10 Tools', 'core-functionality' ),
				path:        'admin.php?page=tptn_tools_page',
				short_title: __( 'Tools', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-top-ten-settings',
				title:       __( 'Top 10 Settings', 'core-functionality' ),
				path:        'admin.php?page=tptn_options_page',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
