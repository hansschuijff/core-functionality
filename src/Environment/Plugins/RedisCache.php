<?php
/**
 * Redis Cache Verification Guard.
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
 * Class RedisCache
 *
 * Represents Redis Cache in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class RedisCache extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'redis-cache';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// The main file creates the plugin instance as soon as it loads.
		return new ProofRequirement( classes: 'Rhubarb\\RedisCache\\Plugin' );
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
				id:      'cf-redis-cache',
				title:   __( 'Redis Cache', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=redis-cache' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Redis Cache Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
