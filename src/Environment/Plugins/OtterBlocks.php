<?php
/**
 * Otter – Page Builder Blocks & Extensions for Gutenberg Verification Guard.
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
 * Class OtterBlocks
 *
 * Represents Otter – Page Builder Blocks & Extensions for Gutenberg in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class OtterBlocks extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'otter-blocks';
	}

	/**
	 * Returns the name the plugin is known by (its header says "Otter – Page Builder Blocks & Extensions for Gutenberg").
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Otter Blocks';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'OTTER_BLOCKS_VERSION' );
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
				id:          'cf-otter-blocks',
				title:       __( 'Otter Blocks', 'core-functionality' ),
				path:        'admin.php?page=otter',
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-otter-blocks-blocks',
				title:       __( 'Otter Blocks: Blocks', 'core-functionality' ),
				path:        'admin.php?page=otter-blocks-toggle',
				short_title: __( 'Blocks', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-otter-blocks-submissions',
				title:       __( 'Form Submissions', 'core-functionality' ),
				path:        'edit.php?post_type=otter_form_record',
				short_title: __( 'Submissions', 'core-functionality' ),
			),
		);
	}
}
