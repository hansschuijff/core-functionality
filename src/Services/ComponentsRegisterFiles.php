<?php
/**
 * File containing the ComponentsRegisterFiles class.
 *
 * @package DeWittePrins\CoreFunctionality\Services
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Services;

use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Enums\Register;
use DeWittePrins\CoreFunctionality\Enums\RegisterFile;

use function __;
use function is_dir;
use function rtrim;
use function sprintf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ComponentsRegisterFiles
 *
 * The access of the ComponentsRegister to the register files in kernel/, as SettingsFiles is the
 * access of Settings to the settings files. It knows which register lives in which file; the
 * FileService does the reading and writing.
 *
 * The files are a fallback for the database options and travel with git: they are only written
 * where the folder is writable (on production it often is not, and the option keeps the change).
 *
 * @since 1.0.0
 */
class ComponentsRegisterFiles {

	/**
	 * The folder of the register files, with a trailing slash.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * ComponentsRegisterFiles Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The main plugin instance (paths, notices and the FileService).
	 */
	public function __construct( private readonly Plugin $plugin ) {
		$this->dir = rtrim( $this->get_registers_dir(), '/\\' ) . '/';
	}

	/**
	 * Returns the content of a register file.
	 *
	 * @since  1.0.0
	 * @param  Register $register The register.
	 * @return array              The register, or an empty array when the file is missing or unreadable.
	 */
	public function get_register( Register $register ): array {
		return $this->plugin->file_service->read( $this->get_path( $register ) ) ?? array();
	}

	/**
	 * Writes a register file.
	 *
	 * @since  1.0.0
	 * @param  Register     $register      The register.
	 * @param  array<mixed> $register_data The register content.
	 * @return string|bool                 A success message, or false when nothing was written.
	 */
	public function save_register( Register $register, array $register_data ): string|bool {
		if ( empty( $register_data ) ) {
			return false;
		}

		$path = $this->get_path( $register );

		// A folder that may not be written (common on production) is no error: the option keeps the change.
		if ( ! $this->plugin->file_service->is_writable( $path ) ) {
			return false;
		}

		if ( ! $this->plugin->file_service->write( $path, $register_data ) ) {
			$this->plugin->notices->add(
				'error',
				sprintf(
					/* translators: %1$s: path of the register file. */
					__( 'Critical Error: Failed to write updates toward configuration file: %1$s.', 'core-functionality' ),
					$path
				)
			);
			return false;
		}

		return __( 'Settings saved successfully. Remember to commit changes in Git! 🚀', 'core-functionality' );
	}

	/**
	 * Returns the full path of a register file.
	 *
	 * @since  1.0.0
	 * @param  Register $register The register.
	 * @return string             The path, or an empty string for a register without a file.
	 */
	private function get_path( Register $register ): string {
		$file = match ( $register ) {
			Register::COMPONENTS => RegisterFile::COMPONENTS->value,
			Register::ENABLED    => RegisterFile::ENABLED->value,
			Register::FRONTEND   => RegisterFile::FRONTEND->value,
			Register::ADMIN      => RegisterFile::ADMIN->value,
			default              => '',
		};

		return '' !== $file ? $this->dir . $file : '';
	}

	/**
	 * Returns the folder of the register files.
	 *
	 * Falls back to the settings folder, with a notice, when the kernel folder is missing.
	 *
	 * @since  1.0.0
	 * @return string The folder.
	 */
	private function get_registers_dir(): string {
		$path = $this->plugin->get_data( 'kernel-settings-dir' );

		if ( empty( $path ) || ! is_dir( $path ) ) {
			$this->plugin->notices->add(
				'error',
				__( 'Path to the component registers could not be retrieved, fallback paths used. Please inform Support', 'core-functionality' )
			);

			$path = $this->plugin->get_data( 'settings-dir' );
		}

		return (string) $path;
	}
}
