<?php
/**
 * Plugin Environment Data Provider Contract.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Plugins\Interfaces
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins\Interfaces;

use DeWittePrins\CoreFunctionality\Environment\Interfaces\DependencyInterface;
use DeWittePrins\CoreFunctionality\Environment\Interfaces\PostTypeProviderInterface;
use DeWittePrins\CoreFunctionality\Environment\Interfaces\ToolbarProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface PluginInterface
 *
 * @since 1.0.0
 */
interface PluginInterface extends DependencyInterface, ToolbarProviderInterface, PostTypeProviderInterface {
}
