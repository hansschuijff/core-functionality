<?php
/**
 * Nobuna Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
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
 * Class Nobuna
 *
 * Represents Nobuna in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class Nobuna extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'nobuna';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'nobuna_get_php_version' );
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
			new ToolbarShortcut(
				id:          'cf-nobuna',
				title:       __( 'Nobuna', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=nobuna-plugins' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Nobuna Plugins', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
