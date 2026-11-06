<?php
/**
 * File containing the TitleForm Enum.
 *
 * @package DeWittePrins\CoreFunctionality\Enums
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.1.3
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which title a shortcut in the toolbar menu shows when it has no title of its own.
 *
 * Stored per node in the menu builder's tree (key `title-form`); a node without the key is AUTO,
 * so menus saved before this choice existed keep their titles.
 *
 * @since 1.1.3
 */
enum TitleForm: string {

	/**
	 * The short title under a parent of the same group ("Settings" under WooCommerce), else the full one.
	 */
	case AUTO = '';

	/**
	 * Always the short title, where the shortcut has one.
	 */
	case SHORT = 'short';

	/**
	 * Always the full title ("WooCommerce Settings").
	 */
	case FULL = 'full';

	/**
	 * The name of the plugin or theme ("WooCommerce"), for a parent that already says what it is
	 * ("Settings").
	 */
	case SOURCE = 'source';

	/**
	 * Returns the title form of a node in the saved tree.
	 *
	 * @since  1.1.3
	 * @param  array $node_meta The node settings from the saved tree.
	 * @return self The stored form, or AUTO when it is missing or unknown.
	 */
	public static function of_node( array $node_meta ): self {
		return self::tryFrom( (string) ( $node_meta['title-form'] ?? '' ) ) ?? self::AUTO;
	}
}
