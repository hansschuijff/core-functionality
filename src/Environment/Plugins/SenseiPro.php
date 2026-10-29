<?php
/**
 * Sensei Pro (WC Paid Courses) Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

/**
 * Class SenseiPro
 *
 * Sensei Pro (WC Paid Courses, plugin folder woothemes-sensei) bundles Sensei LMS and loads it
 * itself when the standalone Sensei LMS plugin is not active. It therefore supplies the same
 * course links as the SenseiLms guard, which it inherits.
 */
class SenseiPro extends SenseiLms {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'sensei-pro';
	}

	/**
	 * Returns no post types: the course post types belong to Sensei LMS, which this guard extends.
	 *
	 * @since  1.0.0
	 * @return string[] Empty array.
	 */
	#[Override]
	public function get_post_types(): array {
		return array();
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Both are defined by the bundled plugins once the bundle has loaded them (plugins_loaded 1).
		return new ProofRequirement( constants: array( 'SENSEI_LMS_VERSION', 'SENSEI_PRO_VERSION' ) );
	}
}
