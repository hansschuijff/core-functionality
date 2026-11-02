<?php
/**
 * External Out-Of-Bounds Push Notice Orchestrator.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Notifiers
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Notifiers;

use DeWittePrins\CoreFunctionality\Enums\CoreFunction;
use DeWittePrins\CoreFunctionality\Notifiers\Interfaces\PushNoticeInterface;
use DeWittePrins\CoreFunctionality\Notifiers\Drivers\Telegram;
use DeWittePrins\CoreFunctionality\Services\ClassParser;
use DeWittePrins\CoreFunctionality\Services\ReleaseRegister;
use DeWittePrins\CoreFunctionality\Services\Settings;

use function is_array;
use function is_string;

/**
 * Class PushNotice
 *
 * Orchestrates external push alerts by matching settings context to configured driver instances.
 *
 * @since 1.0.0
 */
class PushNotice {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Settings        $settings         The injected shared framework settings gateway.
	 * @param ReleaseRegister $release_register Tells whether push notices are on here.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly ReleaseRegister $release_register,
	) {}

	/**
	 * Send a message to the admin to alert to an event.
	 *
	 * Switched off, this sends nothing, whoever asks and whatever settings are stored: the caller
	 * does not have to check.
	 *
	 * @since 1.0.0
	 * @param string $message The alert text.
	 * @return bool True If successfully sent, false otherwise.
	 */
	public function send( string $message ): bool {
		if ( ! $this->release_register->is_core_function_on( CoreFunction::PUSH_NOTICES ) ) {
			return false;
		}

		$driver = $this->get_current_driver();
		if ( false === $driver ) {
			return false; // No valid driver implementation found, unable to notify.
		}

		$driver_settings = $this->get_driver_settings( $driver );
		if ( false === $driver_settings ) {
			return false; // Driver settings are corrupt or not configured yet.
		}

		// Rocket launch trigger: execute the driver passing the local instance configuration forward.
		return ( new $driver( $driver_settings ) )->send( $message );
	}

	/**
	 * Resolves the active Fully Qualified Class Name of the messaging driver.
	 *
	 * @since 1.0.0
	 * @return string|false Verified driver FQCN string, or false on error.
	 */
	public function get_current_driver(): string|false {
		$driver = $this->settings->get( 'push-notice-driver' );

		if ( empty( $driver ) ) {
			$driver = Telegram::class; // Fallback to safe core default.
		}

		if ( ! is_string( $driver ) ) {
			return false;
		}

		// ClassParser handles class_exists() internally before verifying the contract.
		if ( ! ClassParser::is_implementation_of( $driver, PushNoticeInterface::class ) ) {
			return false;
		}

		return $driver;
	}

	/**
	 * Retrieves and validates the stored dataset settings for a specific target driver.
	 *
	 * @since 1.0.0
	 * @param string $driver The target driver FQCN string.
	 * @return array|false The settings array map on success, false otherwise.
	 */
	public function get_driver_settings( string $driver ): array|false {
		// ClassParser handles class_exists() internally before verifying the contract.
		if ( ! ClassParser::is_implementation_of( $driver, PushNoticeInterface::class ) ) {
			return false;
		}

		// Transform the class mapping footprint: 'Telegram' becomes 'telegram'.
		$slug = ClassParser::fqcn_remove_namespace( $driver );
		$slug = ClassParser::camel_to_kebab( $slug );

		// Use the clean kebabcase slug context to dynamically request the unique settings block path.
		$driver_settings = $this->settings->get( 'push-notice-driver-settings-' . $slug );

		if ( ! is_array( $driver_settings ) ) {
			return false;
		}

		return $driver_settings;
	}
}
