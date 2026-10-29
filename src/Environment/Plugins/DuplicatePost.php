<?php
/**
 * Yoast Duplicate Post Verification Guard.
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
 * Class DuplicatePost
 *
 * Reports whether Yoast Duplicate Post is active and started. Its Rewrite & Republish copies let
 * published content be changed in a copy that only replaces the original when it is published.
 *
 * @since 1.0.0
 */
class DuplicatePost extends AbstractPlugin {

	/**
	 * Returns the environment id of Yoast Duplicate Post.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'duplicate-post';
	}

	/**
	 * Returns what must exist to prove Duplicate Post has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Defined in the main plugin file itself, so it exists once the plugin is loaded.
		return new ProofRequirement( constants: 'DUPLICATE_POST_CURRENT_VERSION' );
	}

	/**
	 * Returns the admin toolbar links Duplicate Post offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-duplicate-post-settings',
				title:       __( 'Duplicate Post Settings', 'core-functionality' ),
				href:        admin_url( 'options-general.php?page=duplicatepost' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Duplicate Post Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Duplicate Post', 'core-functionality' ),
			),
		);
	}

	/**
	 * Returns the admin menu blocks Duplicate Post offers (none).
	 *
	 * @since  1.0.0
	 * @return array Empty array.
	 */
	#[Override]
	protected function get_menu_nodes(): array {
		return array();
	}
}
