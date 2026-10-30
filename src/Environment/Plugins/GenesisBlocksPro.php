<?php
/**
 * Genesis Blocks Pro Verification Guard.
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
 * Class GenesisBlocksPro
 *
 * Represents Genesis Blocks Pro in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class GenesisBlocksPro extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'genesis-blocks-pro';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'Genesis\\PageBuilder\\main_plugin_file' );
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
				id:          'cf-genesis-blocks-pro',
				title:       __( 'Genesis Blocks', 'core-functionality' ),
				path:        'admin.php?page=genesis-blocks-getting-started',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-blocks-pro-settings',
				title:       __( 'Genesis Blocks Settings', 'core-functionality' ),
				path:        'admin.php?page=genesis-blocks-settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-blocks-pro-portfolio',
				title:       __( 'Portfolio Items', 'core-functionality' ),
				path:        'edit.php?post_type=gpb_portfolio',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-blocks-pro-portfolio-new',
				title:       __( 'New Portfolio Item', 'core-functionality' ),
				path:        'post-new.php?post_type=gpb_portfolio',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-genesis-blocks-pro-portfolio-types',
				title:       __( 'Portfolio Types', 'core-functionality' ),
				path:        'edit-tags.php?taxonomy=gpb_portfolio_type&post_type=gpb_portfolio',
			),
		);
	}
}
