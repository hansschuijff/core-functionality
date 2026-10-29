<?php
/**
 * Post Type Provider Contract.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Interfaces
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PostTypeProviderInterface
 *
 * A guard that knows which post types its plugin (or the core) registers. Features use this
 * through the PostTypeRegister to offer choices per plugin instead of per post type. The list
 * holds plain names only, so it also works when the plugin is not active.
 *
 * @since 1.0.0
 */
interface PostTypeProviderInterface {

	/**
	 * Returns the post types this plugin registers.
	 *
	 * @since  1.0.0
	 * @return string[] Post type names.
	 */
	public function get_post_types(): array;
}
