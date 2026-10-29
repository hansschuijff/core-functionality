<?php
/**
 * Abstract Theme Environment Guard Component.
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

use DeWittePrins\CoreFunctionality\Environment\Themes\Interfaces\ThemeInterface;
use DeWittePrins\CoreFunctionality\Environment\Themes\Services\ThemeInspector;
use DeWittePrins\CoreFunctionality\Environment\EnvironmentLookup;
use DeWittePrins\CoreFunctionality\Environment\Registers\AdminMenuRegister;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use DeWittePrins\CoreFunctionality\Services\ClassParser;

use function is_array;
use function str_replace;
use function ucwords;

/**
 * Class AbstractTheme
 *
 * Provides base implementation for tracking and verifying active theme blueprints.
 *
 * @since 1.0.0
 */
abstract class AbstractTheme implements ThemeInterface {

	/**
	 * The configuration data of the theme.
	 *
	 * @var array{name: string, author?: string}
	 */
	protected array $theme = array( 'name' => '' );

	/**
	 * Class Constructor.
	 *
	 * @since 1.0.0
	 * @param EnvironmentLookup $lookup The environment registry lookup service.
	 */
	public function __construct( readonly EnvironmentLookup $lookup ) {
		$clean_id = ClassParser::get_clean_id( $this->get_id() );
		$theme    = $this->lookup->get_theme_name( $clean_id );

		if ( is_array( $theme ) && ! empty( $theme['name'] ) ) {
			$this->theme = $theme;
		}
	}

	/**
	 * Inspects theme parameters using the centralized service.
	 *
	 * Feeds the admin menu register once readiness is affirmatively verified. The toolbar nodes go
	 * to the catalogue through get_toolbar_nodes(), active or not.
	 *
	 * @since 1.0.0
	 * @return bool True if the theme config matches the environment, false otherwise.
	 */
	public function is_ready(): bool {
		if ( empty( $this->theme['name'] ) ) {
			return false;
		}

		// Guard 1: Lightweight core check verifying if the theme is active in WordPress.
		if ( ! ThemeInspector::is_theme_active( $this->theme ) ) {
			return false;
		}

		// Guard 2: Deep proof checks (directory layouts, specific templates, or framework states).
		if ( ! $this->has_proof_of_life() ) {
			return false;
		}

		AdminMenuRegister::register( $this->get_id(), $this->get_menu_nodes() );

		return true;
	}

	/**
	 * Checks whether the theme is installed, active or not.
	 *
	 * @since  1.0.0
	 * @return bool True if a theme with this name (and author) is installed.
	 */
	public function is_installed(): bool {
		if ( empty( $this->theme['name'] ) ) {
			return false;
		}

		return ThemeInspector::is_theme_installed( $this->theme['name'], $this->theme['author'] ?? '' );
	}

	/**
	 * Returns the theme's name from the environment index, or its id made readable.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	public function get_name(): string {
		if ( ! empty( $this->theme['name'] ) ) {
			return $this->theme['name'];
		}

		return ucwords( str_replace( '-', ' ', ClassParser::get_clean_id( $this->get_id() ) ) );
	}

	/**
	 * Default fallback proof of life check for themes using the structured DTO.
	 *
	 * Child classes should override get_proof_requirements() to provide defensive criteria.
	 *
	 * @since 1.0.0
	 * @return bool True if all criteria pass, false otherwise.
	 */
	public function has_proof_of_life(): bool {
		return ThemeInspector::must_exist( $this->get_proof_requirements() );
	}

	/**
	 * Defines the technical proof criteria required for verification.
	 *
	 * Replicated as a safe default fallback. Overriding this prevents boilerplate in child components.
	 *
	 * @return ProofRequirement The structured proof criteria object.
	 */
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement();
	}

	/**
	 * Checks if the currently active theme is an FSE block theme.
	 *
	 * @since 1.0.0
	 * @return bool True if it is a block theme, false otherwise.
	 */
	protected function is_block_theme(): bool {
		return ThemeInspector::is_block_theme();
	}

	/**
	 * Checks if the currently active theme is a classic/legacy theme.
	 *
	 * @since 1.0.0
	 * @return bool True if it is a classic theme, false otherwise.
	 */
	protected function is_legacy_theme(): bool {
		return ThemeInspector::is_legacy_theme();
	}

	/**
	 * Returns the admin menu blocks this theme offers; none unless a guard says so.
	 *
	 * @since  1.0.0
	 * @return \DeWittePrins\CoreFunctionality\DTO\AdminMenuBlock[] The menu blocks.
	 */
	protected function get_menu_nodes(): array {
		return array();
	}
}
