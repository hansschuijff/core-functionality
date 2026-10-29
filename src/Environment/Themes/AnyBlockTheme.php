<?php
/**
 * Any Block Theme Verification Guard.
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
	exit;
}

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\Themes\Services\ThemeInspector;
use DeWittePrins\CoreFunctionality\Environment\Registers\AdminMenuRegister;
use Override;

use function __;
use function admin_url;

/**
 * Class AnyBlockTheme
 *
 * Evaluates whether the currently running system layout is securely powered
 * by any modern WordPress Full Site Editing (FSE) block theme architecture.
 *
 * @since 1.0.0
 */
class AnyBlockTheme extends AbstractTheme {

	/**
	 * Unique identifier token for this specific ecosystem component.
	 *
	 * @since 1.0.0
	 * @return string The blueprint identification token.
	 */
	#[Override]
	public function get_id(): string {
		return 'any-block-theme';
	}

	/**
	 * Checks for any active block theme.
	 *
	 * Overrides the parent check, which needs a theme name from environment-index.php;
	 * this guard applies to every block theme, so it has no entry there.
	 *
	 * @since 1.0.0
	 * @return bool True if a block theme is active, false otherwise.
	 */
	#[Override]
	public function is_ready(): bool {
		if ( ! $this->has_proof_of_life() ) {
			return false;
		}

		AdminMenuRegister::register( $this->get_id(), $this->get_menu_nodes() );

		return true;
	}

	/**
	 * Checks whether any block theme is installed.
	 *
	 * @since  1.0.0
	 * @return bool True if at least one installed theme is a block theme.
	 */
	#[Override]
	public function is_installed(): bool {
		return ThemeInspector::is_block_theme_installed();
	}

	/**
	 * Overrides the default proof of life check to poll for FSE block theme presence.
	 *
	 * Bypasses the default lookup constraints since this applies globally to any FSE setup.
	 *
	 * @since 1.0.0
	 * @return bool True if an FSE block theme is active, false otherwise.
	 */
	#[Override]
	public function has_proof_of_life(): bool {
		return ThemeInspector::is_block_theme();
	}

	/**
	 * Compiles and returns the available pool of toolbar shortcuts this theme can offer.
	 *
	 * Transforms your link data into a type-safe ToolbarShortcut DTO block.
	 *
	 * @since 1.0.0
	 * @return ToolbarShortcut[] Collection of theme toolbar shortcut DTOs.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-block-theme-site-editor',
				title:   __( 'Site editor', 'core-functionality' ),
				href:    admin_url( 'site-editor.php' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Site editor', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
