<?php
/**
 * TablePress Verification Guard.
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
 * Class Tablepress
 *
 * Represents TablePress in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class Tablepress extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'tablepress';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'TABLEPRESS_ABSPATH' );
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
				id:          'cf-tablepress',
				title:       __( 'Tables', 'core-functionality' ),
				path:        'admin.php?page=tablepress',
				label:       __( 'TablePress', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tablepress-new',
				title:       __( 'New Table', 'core-functionality' ),
				path:        'admin.php?page=tablepress_add',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tablepress-import',
				title:       __( 'Import Table', 'core-functionality' ),
				path:        'admin.php?page=tablepress_import',
				short_title: __( 'Import', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tablepress-export',
				title:       __( 'Export Table', 'core-functionality' ),
				path:        'admin.php?page=tablepress_export',
				short_title: __( 'Export', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tablepress-settings',
				title:       __( 'TablePress Settings', 'core-functionality' ),
				path:        'admin.php?page=tablepress_options',
				short_title: __( 'Settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-tablepress-about',
				title:       __( 'About TablePress', 'core-functionality' ),
				path:        'admin.php?page=tablepress_about',
			),
		);
	}
}
