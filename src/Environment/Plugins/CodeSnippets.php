<?php
/**
 * Code Snippets Verification Guard.
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
 * Class CodeSnippets
 *
 * Represents Code Snippets (and Code Snippets Pro) in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class CodeSnippets extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'code-snippets';
	}

	/**
	 * Returns the name the plugin is known by (its header says "Code Snippets").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Code Snippets';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'CODE_SNIPPETS_VERSION' );
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
				id:          'cf-code-snippets',
				title:       __( 'Snippets', 'core-functionality' ),
				path:        'admin.php?page=snippets',
				label:       __( 'Code Snippets', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-code-snippets-new',
				title:       __( 'New Snippet', 'core-functionality' ),
				path:        'admin.php?page=add-snippet',
				short_title: __( 'New', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-code-snippets-import',
				title:       __( 'Import Snippets', 'core-functionality' ),
				path:        'admin.php?page=import-code-snippets',
				short_title: __( 'Import', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-code-snippets-settings',
				title:       __( 'Code Snippets Settings', 'core-functionality' ),
				path:        'admin.php?page=snippets-settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
