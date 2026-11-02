<?php
/**
 * File containing the CoreFunction Enum.
 *
 * @package DeWittePrins\CoreFunctionality\Enums
 * @author  Hans Schuijff @hansschuijff
 * @license GPL-2.0
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function __;

/**
 * Enum of the plugin's own functions that can be switched per environment.
 *
 * They are not modules or features: the scan does not find them, so this is their list. Whether one
 * is on is answered by ReleaseRegister::is_core_function_on(), the only place that decides it.
 *
 * @since 1.1.0
 */
enum CoreFunction: string {

	case DEVELOPER_TOOLS    = 'developer-tools';
	case FEATURE_ACTIVATION = 'feature-activation';
	case PUSH_NOTICES       = 'push-notices';
	case OPTIONS_MANAGEMENT = 'options-management';

	/**
	 * Returns the translated name of the function.
	 *
	 * @since  1.1.0
	 * @return string The name.
	 */
	public function get_label(): string {
		return match ( $this ) {
			self::DEVELOPER_TOOLS    => __( 'Release Management and feature scan', 'core-functionality' ),
			self::FEATURE_ACTIVATION => __( 'Feature Activation', 'core-functionality' ),
			self::PUSH_NOTICES       => __( 'Push Notice Services', 'core-functionality' ),
			self::OPTIONS_MANAGEMENT => __( 'Options Vault', 'core-functionality' ),
		};
	}

	/**
	 * Returns the translated explanation of the function, with the reason when it is locked.
	 *
	 * @since  1.1.0
	 * @return string The description.
	 */
	public function get_description(): string {
		return match ( $this ) {
			self::DEVELOPER_TOOLS    => __( 'Locked: these only belong on a development environment.', 'core-functionality' ),
			self::FEATURE_ACTIVATION => __( 'Locked: without this tab a site cannot switch its features on or off.', 'core-functionality' ),
			self::PUSH_NOTICES       => __( 'The settings tab of the push notices.', 'core-functionality' ),
			self::OPTIONS_MANAGEMENT => __( 'Only the settings tab for managing the option keys. The vault itself keeps working; when this is off, options stay in the quarantine or under their earlier keys.', 'core-functionality' ),
		};
	}

	/**
	 * Returns the state the code forces outside a development environment.
	 *
	 * A lock goes above the release file, the configuration file and the filter.
	 *
	 * @since  1.1.0
	 * @return bool|null True for always on, false for always off, null when it can be set.
	 */
	public function get_locked_state(): ?bool {
		return match ( $this ) {
			self::DEVELOPER_TOOLS    => false,
			self::FEATURE_ACTIVATION => true,
			default                  => null,
		};
	}

	/**
	 * Returns the key of the function in the release settings.
	 *
	 * @since  1.1.0
	 * @return string The key; the prefix keeps it apart from the class names of the components.
	 */
	public function get_release_key(): string {
		return 'core:' . $this->value;
	}
}
