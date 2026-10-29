<?php
/**
 * Reader Experience Verification Guard.
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
 * Class ReaderExperience
 *
 * Reports whether Hans's Reader Experience plugin (reading aids, sharing, ratings and feedback)
 * is active and started, and supplies its feedback page as a menu item.
 *
 * @since 1.0.0
 */
class ReaderExperience extends AbstractPlugin {

	/**
	 * Returns the environment id of Reader Experience.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'reader-experience';
	}

	/**
	 * Returns what must exist to prove Reader Experience has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: 'ReaderExperience\Plugin' );
	}

	/**
	 * Returns the admin toolbar links Reader Experience offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-reader-experience-feedback',
				title:       __( 'Reader Feedback', 'core-functionality' ),
				href:        admin_url( 'edit-comments.php?page=dwp-rx-feedback' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Reader Feedback', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Feedback', 'core-functionality' ),
			),
		);
	}
}
