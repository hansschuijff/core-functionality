<?php
/**
 * Configuration Router Index for this plugin.
 *
 * Maps abstract feature configuration keys to localized file system targets.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(

	/**
	 * Globals & Core Framework Index Registry.
	 */
	'environment-index'              => 'environment-index',
	'push-notice-available-drivers'  => 'push-notice-drivers',

	/**
	 * Feature: Activewoo->KeepLicenseAlive.
	 */
	'activewoo-license-data'         => 'license',

	/**
	 * Feature: AdminToolbar->MenuBuilder. The default menu, written by "Save as default".
	 */
	'menu-builder-hierarchical-tree' => 'menu-builder',

	/**
	 * Feature: ReaderExperience->Configuration. The defaults of its settings tab.
	 */
	'reader-experience-settings'     => 'reader-experience-settings',

	/**
	 * Feature: Events->TemplateFallback. The defaults of the Event templates tab.
	 */
	'events-settings'                => 'events-settings',

	/**
	 * Feature: AiAccess->ContentEditing. The defaults of the AI Access tab.
	 */
	'ai-access-settings'             => 'ai-access-settings',

	/**
	 * Feature: module->feature
	 */

);
