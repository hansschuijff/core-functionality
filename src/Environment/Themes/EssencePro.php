<?php
/**
 * Essence Pro Child Theme Verification Guard.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Themes
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Themes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class EssencePro
 *
 * Assesses active styling inheritance profiles to verify if the running
 * design instance matches the specialized Essence Pro layout footprint.
 *
 * @since 1.0.0
 */
class EssencePro extends AbstractTheme {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'essence-pro-theme';
	}

	/**
	 * Declares specific technical proof criteria required for theme verification.
	 *
	 * Optional: You can extend this with specific framework classes, Genesis functions,
	 * or version constants if you want to make it extra defensive.
	 *
	 * @since 1.0.0
	 * @return ProofRequirement The structured proof criteria object.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Essence Pro defines no constant or function of its own to check; the activation check in
		// ThemeInspector (template and stylesheet options) is the proof here.
		return new ProofRequirement();
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this theme can offer.
	 *
	 * Transforms your custom link data directly into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of theme toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-essence-pro',
				title:   __( '⚡ Essence Pro', 'core-functionality' ),
				href:    admin_url( 'customize.php' ), // The customizer is where Essence Pro is set up.
				label:   __( 'Essence Pro Theme Customizer', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
