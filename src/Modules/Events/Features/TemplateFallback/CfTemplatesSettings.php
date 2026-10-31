<?php
/**
 * Site Event Templates Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Events\Features\TemplateFallback
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateFallback;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Modules\Events\Events;
use DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateMonitor\TemplateMonitor;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;
use ZipArchive;

use const PHP_URL_HOST;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

use function __;
use function _n;
use function array_diff;
use function array_keys;
use function array_values;
use function class_exists;
use function count;
use function current_user_can;
use function defined;
use function checked;
use function delete_transient;
use function dirname;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function file_exists;
use function filesize;
use function function_exists;
use function get_filesystem_method;
use function get_temp_dir;
use function gmdate;
use function header;
use function home_url;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_uploaded_file;
use function is_wp_error;
use function ksort;
use function list_files;
use function nocache_headers;
use function opcache_invalidate;
use function readfile;
use function sanitize_file_name;
use function sanitize_key;
use function scandir;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trailingslashit;
use function unzip_file;
use function wp_delete_file;
use function wp_generate_password;
use function wp_mkdir_p;
use function wp_normalize_path;
use function wp_parse_url;
use function wp_tempnam;
use function wp_unslash;
use function WP_Filesystem;

/**
 * Class CfTemplatesSettings
 *
 * The settings tab of the CF event templates: shows what is in the folder and whether a theme
 * overrides it, replaces the whole folder from a zip (like a theme or plugin is replaced, so it is
 * always clear what is there), keeps the version before as a backup that can be swapped back, and
 * downloads the CF templates or every template the site uses now (themes included) as a zip. It
 * also holds the choice whether the template check reports theme copies of CF templates.
 *
 * Changing the folder takes the right to install plugins, file changes allowed on the site and
 * direct file access; downloading and the report choice only the rights of the settings page.
 *
 * @since 1.0.0
 */
class CfTemplatesSettings implements SettingsSectionInterface {

	/**
	 * The name of the action buttons.
	 *
	 * @var string
	 */
	private const ACTION = 'dwp_cf_templates_action';

	/**
	 * The name of the file field.
	 *
	 * @var string
	 */
	private const UPLOAD = 'dwp_cf_templates_zip';

	/**
	 * The actions the buttons send.
	 *
	 * @var string[]
	 */
	private const ACTIONS = array( 'upload', 'restore', 'download-cf', 'download-used', 'save-options' );

	/**
	 * The settings key of the choices of this tab (defaults in settings/modules/events/).
	 *
	 * @var string
	 */
	public const SETTINGS_KEY = 'events-settings';

	/**
	 * The name of the report checkbox.
	 *
	 * @var string
	 */
	private const REPORT = 'dwp_cf_report_theme_copies';

	/**
	 * CfTemplatesSettings Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns the id of this settings section (used in the page URL).
	 *
	 * @since  1.0.0
	 * @return string The section id.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-event-templates';
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-media-code';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Templates', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Here you can load custom templates that replace built-in templates of The Events Calendar and Event Tickets. It gives you a place to make changes independent of the theme. A template stored here is only loaded when the active theme does not have it built in. Make theme-specific changes the usual way in a parent or child theme; they take precedence.', 'core-functionality' );
	}

	/**
	 * Returns the stylesheets this tab needs.
	 *
	 * @since  1.0.0
	 * @return array The stylesheets to enqueue.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array();
	}

	/**
	 * Returns the scripts this tab needs.
	 *
	 * @since  1.0.0
	 * @return array The scripts to enqueue.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array();
	}

	/**
	 * Returns the folder that holds the version before the last change.
	 *
	 * @since  1.0.0
	 * @return string The folder.
	 */
	private static function previous_dir(): string {
		return TemplateLocations::cf_dir() . '.previous';
	}

	/**
	 * Returns the templates in a folder, sorted.
	 *
	 * @since  1.0.0
	 * @param  string $dir The folder.
	 * @return array<string, string> Path relative to the folder => file.
	 */
	private static function templates_in( string $dir ): array {
		$templates = array();
		foreach ( TemplateLocations::php_files( $dir ) as $file ) {
			$relative = substr( $file, strlen( $dir ) + 1 );
			if ( TemplateLocations::is_template_path( $relative ) ) {
				$templates[ $relative ] = $file;
			}
		}
		ksort( $templates );

		return $templates;
	}

	/**
	 * Returns every template the site uses now: per template the first copy in lookup order.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Template path => file.
	 */
	private static function used_templates(): array {
		$templates = array();
		foreach ( TemplateLocations::collect( true ) as $path => $found ) {
			$templates[ $path ] = $found[0]['file'];
		}
		ksort( $templates );

		return $templates;
	}

	/**
	 * Returns the folder that must be writable: wp-content/core-functionality/, which holds the
	 * templates, the new set and the previous version. Only when it does not exist yet, wp-content
	 * itself, where it is to be made; an administrator need not give more than that.
	 *
	 * @since  1.0.0
	 * @return string The folder.
	 */
	private static function write_context(): string {
		$dir = dirname( TemplateLocations::cf_dir() );

		return is_dir( $dir ) ? $dir : wp_normalize_path( WP_CONTENT_DIR );
	}

	/**
	 * Tells why the folder cannot be changed here, if it cannot.
	 *
	 * @since  1.0.0
	 * @return string The reason, or '' when it can.
	 */
	private function cannot_modify(): string {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return __( 'File changes are turned off on this site (DISALLOW_FILE_MODS).', 'core-functionality' );
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return __( 'Only users who may install plugins can change the CF templates.', 'core-functionality' );
		}

		$context = self::write_context();
		if ( 'direct' === get_filesystem_method( array(), $context ) ) {
			return '';
		}

		$dir = dirname( TemplateLocations::cf_dir() );
		if ( $context === $dir ) {
			/* translators: %s: the folder. */
			return sprintf( __( 'WordPress cannot write in %s. Make this folder writable for the web server.', 'core-functionality' ), $dir );
		}

		/* translators: %s: the folder. */
		return sprintf( __( 'WordPress cannot create %s. Create this folder and make it writable for the web server.', 'core-functionality' ), $dir );
	}

	/**
	 * Outputs the tab.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Not used: this tab has no settings.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$this->render_templates();
		$this->render_downloads();
		$this->render_upload();
		$this->render_previous();
		$this->render_report( $defaults_only );
	}

	/**
	 * Whether the template check reports templates that are in the active theme as well as in the
	 * CF templates. Used by the tab and by the check.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin        The main plugin instance.
	 * @param  bool   $defaults_only Whether to return the default instead of the saved choice.
	 * @return bool True when reported.
	 */
	public static function reports_theme_copies( Plugin $plugin, bool $defaults_only = false ): bool {
		$module   = Events::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );
		$values   = ( is_array( $saved ) ? $saved : array() ) + $defaults;

		return ! empty( $values['report_theme_copies'] ?? true );
	}

	/**
	 * Outputs the options; their save button is in the bar under the form.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	private function render_report( bool $defaults_only ): void {
		$checked = self::reports_theme_copies( $this->plugin, $defaults_only );

		echo '<h2>' . esc_html__( 'Options', 'core-functionality' ) . '</h2>';
		echo '<p><label><input type="checkbox" name="' . esc_attr( self::REPORT ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Report when a template is present in the active theme as well as in Core Functionality', 'core-functionality' ) . '</label></p>';
	}

	/**
	 * Outputs the CF templates and whether each is used or a theme overrides it.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_templates(): void {
		$dir       = TemplateLocations::cf_dir();
		$templates = self::templates_in( $dir );

		echo '<h2>' . esc_html__( 'CF templates', 'core-functionality' ) . '</h2>';
		/* translators: %s: the folder. */
		echo '<p>' . sprintf( esc_html__( 'Folder: %s', 'core-functionality' ), '<code>' . esc_html( $dir ) . '</code>' ) . '</p>';

		if ( array() === $templates ) {
			echo '<p>' . esc_html__( 'There are no CF templates yet. Upload a zip to add them.', 'core-functionality' ) . '</p>';
			return;
		}

		$copies = TemplateLocations::collect( true );

		echo '<table class="widefat striped" style="max-width: 60em;"><thead><tr><th>' . esc_html__( 'Template', 'core-functionality' ) . '</th><th>' . esc_html__( 'Status', 'core-functionality' ) . '</th></tr></thead><tbody>';
		foreach ( array_keys( $templates ) as $path ) {
			echo '<tr><td><code>' . esc_html( $path ) . '</code></td><td>' . esc_html( $this->status( $copies[ $path ][0]['source'] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Describes whether a CF template is used.
	 *
	 * @since  1.0.0
	 * @param  string $source The source of the copy the site uses (child, parent, cf), or '' when
	 *                        the plugin it overrides is not active.
	 * @return string The status.
	 */
	private function status( string $source ): string {
		return match ( $source ) {
			'cf'   => __( 'In use', 'core-functionality' ),
			'child'  => __( 'The child theme overrides it', 'core-functionality' ),
			'parent' => __( 'The parent theme overrides it', 'core-functionality' ),
			default  => __( 'Not used: the plugin is not active', 'core-functionality' ),
		};
	}

	/**
	 * Outputs the download buttons.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_downloads(): void {
		echo '<h2>' . esc_html__( 'Download', 'core-functionality' ) . '</h2>';
		echo '<p>' . esc_html__( 'As a zip with the same folders, ready to change and upload again. The second also holds the copies in the theme, each taken from where the site takes it.', 'core-functionality' ) . '</p>';
		echo '<p>' . $this->button( 'download-cf', __( 'CF templates', 'core-functionality' ) ) . ' ' . $this->button( 'download-used', __( 'Templates in use', 'core-functionality' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in button().
	}

	/**
	 * Outputs the version before the last change and its restore button; or that there is none yet.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_previous(): void {
		$previous = self::previous_dir();

		echo '<h2>' . esc_html__( 'Previous version', 'core-functionality' ) . '</h2>';

		if ( ! is_dir( $previous ) ) {
			echo '<p>' . esc_html__( 'There is no previous version yet. The next upload sets the current templates aside here, so you can go back to them.', 'core-functionality' ) . '</p>';
			return;
		}

		$count = count( self::templates_in( $previous ) );

		/* translators: %d: number of templates. */
		echo '<p>' . esc_html( sprintf( _n( 'The version before the last change is kept (%d template). Restoring it swaps the two, so you can switch back.', 'The version before the last change is kept (%d templates). Restoring it swaps the two, so you can switch back.', $count, 'core-functionality' ), $count ) ) . '</p>';

		if ( '' !== $this->cannot_modify() ) {
			return;
		}

		echo '<p>' . $this->button( 'restore', __( 'Restore previous version', 'core-functionality' ), __( 'Swap the CF templates for the previous version?', 'core-functionality' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in button().
	}

	/**
	 * Outputs the upload field and its button.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_upload(): void {
		echo '<h2>' . esc_html__( 'Upload', 'core-functionality' ) . '</h2>';
		echo '<p>' . esc_html__( 'Load a zip archive with custom templates for The Events Calendar and Event Tickets. Arrange the templates in folders and files the same way you would in a theme, and put them in the root of the zip or in one main folder inside it. All other files are skipped. The upload replaces all previously stored templates at once, like installing a theme or plugin. The previous version is set aside and can be restored.', 'core-functionality' ) . '</p>';

		$reason = $this->cannot_modify();
		if ( '' !== $reason ) {
			echo '<p><em>' . esc_html( $reason ) . '</em></p>';
			return;
		}

		echo '<p><input type="file" name="' . esc_attr( self::UPLOAD ) . '" accept=".zip,application/zip" /> ';
		echo $this->button( 'upload', __( 'Upload', 'core-functionality' ), __( 'Replace all CF templates with the templates in this zip?', 'core-functionality' ), 'button button-primary' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in button().
	}

	/**
	 * Returns an action button.
	 *
	 * @since  1.0.0
	 * @param  string $action  The action it sends.
	 * @param  string $label   The label.
	 * @param  string $confirm A question to confirm first, or ''.
	 * @param  string $class   The button classes.
	 * @return string The button.
	 */
	private function button( string $action, string $label, string $confirm = '', string $class = 'button button-secondary' ): string {
		$onclick = '' !== $confirm ? ' onclick="return confirm(\'' . esc_attr( $confirm ) . '\');"' : '';

		return '<button type="submit" name="' . esc_attr( self::ACTION ) . '" value="' . esc_attr( $action ) . '" class="' . esc_attr( $class ) . '"' . $onclick . '>' . esc_html( $label ) . '</button>';
	}

	/**
	 * Returns the action a button sent.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return string The action, or '' when unknown.
	 */
	private function action( array $posted_data ): string {
		$action = sanitize_key( wp_unslash( (string) ( $posted_data[ self::ACTION ] ?? '' ) ) );

		return in_array( $action, self::ACTIONS, true ) ? $action : '';
	}

	/**
	 * Checks the request before it is carried out.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return bool|WP_Error True when it can go ahead, an error otherwise.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		$action = $this->action( $posted_data );
		if ( '' === $action ) {
			return new WP_Error( 'dwp_cf_templates_action', __( 'Unknown action.', 'core-functionality' ) );
		}

		if ( str_starts_with( $action, 'download' ) ) {
			return class_exists( ZipArchive::class ) ? true : new WP_Error( 'dwp_cf_templates_zip', __( 'The server cannot make zip files (ZipArchive is missing).', 'core-functionality' ) );
		}

		// A choice of the settings page, not a change of files.
		if ( 'save-options' === $action ) {
			return true;
		}

		$reason = $this->cannot_modify();
		if ( '' !== $reason ) {
			return new WP_Error( 'dwp_cf_templates_denied', $reason );
		}

		return 'upload' === $action ? $this->check_upload() : true;
	}

	/**
	 * Checks the uploaded file.
	 *
	 * @since  1.0.0
	 * @return bool|WP_Error True when it is a zip that arrived, an error otherwise.
	 */
	private function check_upload(): bool|WP_Error {
		$file = $_FILES[ self::UPLOAD ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the settings page.

		if ( ! is_array( $file ) || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'dwp_cf_templates_no_file', __( 'Choose a zip file first.', 'core-functionality' ) );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'dwp_cf_templates_upload', __( 'The upload failed. The file may be larger than the server allows.', 'core-functionality' ) );
		}
		if ( ! str_ends_with( strtolower( (string) $file['name'] ), '.zip' ) ) {
			return new WP_Error( 'dwp_cf_templates_not_zip', __( 'Choose a .zip file.', 'core-functionality' ) );
		}

		return true;
	}

	/**
	 * Carries out the action of the button that was pressed.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return string|bool A success message, or false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		return match ( $this->action( $posted_data ) ) {
			'download-cf' => $this->download( self::templates_in( TemplateLocations::cf_dir() ), 'cf' ),
			'download-used' => $this->download( self::used_templates(), 'in-use' ),
			'save-options'   => $this->plugin->settings->save( self::SETTINGS_KEY, array( 'report_theme_copies' => ! empty( $posted_data[ self::REPORT ] ) ) ),
			'restore'       => $this->restore(),
			'upload'        => $this->upload(),
			default         => false,
		};
	}

	/**
	 * Sends templates as a zip and ends the request.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $templates Path in the zip => file.
	 * @param  string                $label     Part of the file name: cf or in-use.
	 * @return bool False when there is nothing to send or the zip fails; otherwise it does not return.
	 */
	private function download( array $templates, string $label ): bool {
		if ( array() === $templates ) {
			return $this->fail( __( 'There are no templates to download.', 'core-functionality' ) );
		}

		$zip_file = wp_tempnam( 'event-templates.zip' );
		$zip      = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return $this->fail( __( 'The zip file could not be made.', 'core-functionality' ) );
		}
		foreach ( $templates as $path => $file ) {
			$zip->addFile( $file, $path );
		}
		$zip->close();

		$host = sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$name = sprintf( 'event-templates-%s-%s-%s.zip', $label, $host, gmdate( 'Ymd-His' ) );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $zip_file ) );
		readfile( $zip_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the zip.
		wp_delete_file( $zip_file );
		exit;
	}

	/**
	 * Replaces the CF templates with the templates in the uploaded zip, as a whole.
	 *
	 * @since  1.0.0
	 * @return string|bool A success message, or false on failure.
	 */
	private function upload(): string|bool {
		global $wp_filesystem;

		if ( ! WP_Filesystem( false, self::write_context() ) ) {
			return $this->fail( __( 'WordPress cannot write files here.', 'core-functionality' ) );
		}

		$tmp    = trailingslashit( wp_normalize_path( get_temp_dir() ) ) . 'dwp-cf-templates-' . wp_generate_password( 8, false, false );
		$result = unzip_file( (string) $_FILES[ self::UPLOAD ]['tmp_name'], $tmp ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Verified by the settings page; checked in check_upload().
		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $tmp, true );
			return $this->fail( $result->get_error_message() );
		}

		$root                   = $this->zip_root( $tmp );
		[ $accepted, $skipped ] = $this->sort_files( $root );
		$new                    = TemplateLocations::cf_dir() . '.new';
		$copied                 = array() !== $accepted && $this->copy_templates( $root, $accepted, $new );
		$wp_filesystem->delete( $tmp, true );

		if ( array() === $accepted ) {
			return $this->fail( __( 'The zip holds no templates: no PHP files in tribe/… or tribe-events/…. Nothing was changed.', 'core-functionality' ) );
		}
		if ( ! $copied || ! $this->swap_in( $new ) ) {
			$wp_filesystem->delete( $new, true );
			return $this->fail( __( 'The CF templates could not be replaced. Nothing was changed.', 'core-functionality' ) );
		}

		$this->after_change();
		if ( array() !== $skipped ) {
			/* translators: %s: the skipped files, separated by commas. */
			$this->plugin->notices->add( 'warning', sprintf( __( 'Skipped, not a template: %s', 'core-functionality' ), implode( ', ', $skipped ) ) );
		}

		/* translators: %d: number of templates. */
		return sprintf( _n( '%d CF template installed; the previous version is kept.', '%d CF templates installed; the previous version is kept.', count( $accepted ), 'core-functionality' ), count( $accepted ) );
	}

	/**
	 * Returns the folder in the unpacked zip that holds the template folders: the zip itself, or
	 * the one folder it encloses everything in.
	 *
	 * @since  1.0.0
	 * @param  string $tmp The folder the zip was unpacked in.
	 * @return string The folder.
	 */
	private function zip_root( string $tmp ): string {
		$entries = array_values( array_diff( (array) scandir( $tmp ), array( '.', '..', '__MACOSX' ) ) );
		if ( 1 !== count( $entries ) || in_array( $entries[0], array( 'tribe', 'tribe-events' ), true ) || ! is_dir( $tmp . '/' . $entries[0] ) ) {
			return $tmp;
		}

		return $tmp . '/' . $entries[0];
	}

	/**
	 * Splits the files in the unpacked zip into templates and other files.
	 *
	 * @since  1.0.0
	 * @param  string $root The folder that holds the template folders.
	 * @return array{0: string[], 1: string[]} The templates and the skipped files, relative to the folder.
	 */
	private function sort_files( string $root ): array {
		$accepted = array();
		$skipped  = array();
		foreach ( (array) list_files( $root, 100 ) as $file ) {
			$relative = substr( wp_normalize_path( (string) $file ), strlen( $root ) + 1 );
			if ( str_starts_with( $relative, '__MACOSX/' ) || str_ends_with( $relative, '/' ) ) {
				continue;
			}
			if ( TemplateLocations::is_template_path( $relative ) ) {
				$accepted[] = $relative;
				continue;
			}
			$skipped[] = $relative;
		}

		return array( $accepted, $skipped );
	}

	/**
	 * Copies the templates into a new folder.
	 *
	 * @since  1.0.0
	 * @param  string   $root      The folder that holds the template folders.
	 * @param  string[] $templates The templates, relative to that folder.
	 * @param  string   $new       The new folder (emptied first).
	 * @return bool True when every template was copied.
	 */
	private function copy_templates( string $root, array $templates, string $new ): bool {
		global $wp_filesystem;

		$wp_filesystem->delete( $new, true );
		foreach ( $templates as $relative ) {
			$target = $new . '/' . $relative;
			if ( ! wp_mkdir_p( dirname( $target ) ) || ! $wp_filesystem->copy( $root . '/' . $relative, $target, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Puts a new folder in place of the CF templates; the current ones become the previous version.
	 *
	 * @since  1.0.0
	 * @param  string $new The new folder.
	 * @return bool True when it is in place.
	 */
	private function swap_in( string $new ): bool {
		global $wp_filesystem;

		$dir      = TemplateLocations::cf_dir();
		$previous = self::previous_dir();

		$this->protect( dirname( $dir ) );
		if ( is_dir( $dir ) ) {
			$wp_filesystem->delete( $previous, true );
			if ( ! $wp_filesystem->move( $dir, $previous ) ) {
				return false;
			}
		}

		return $wp_filesystem->move( $new, $dir );
	}

	/**
	 * Swaps the CF templates and the previous version.
	 *
	 * @since  1.0.0
	 * @return string|bool A success message, or false on failure.
	 */
	private function restore(): string|bool {
		global $wp_filesystem;

		$dir      = TemplateLocations::cf_dir();
		$previous = self::previous_dir();
		$swap     = $dir . '.swap';

		if ( ! is_dir( $previous ) ) {
			return $this->fail( __( 'There is no previous version.', 'core-functionality' ) );
		}
		if ( ! WP_Filesystem( false, self::write_context() ) ) {
			return $this->fail( __( 'WordPress cannot write files here.', 'core-functionality' ) );
		}

		$wp_filesystem->delete( $swap, true );
		if ( is_dir( $dir ) && ! $wp_filesystem->move( $dir, $swap ) ) {
			return $this->fail( __( 'The previous version could not be restored. Nothing was changed.', 'core-functionality' ) );
		}
		if ( ! $wp_filesystem->move( $previous, $dir ) ) {
			$wp_filesystem->move( $swap, $dir );
			return $this->fail( __( 'The previous version could not be restored. Nothing was changed.', 'core-functionality' ) );
		}
		if ( is_dir( $swap ) ) {
			$wp_filesystem->move( $swap, $previous );
		}

		$this->after_change();

		return __( 'The previous version is restored; the replaced version is now the previous one.', 'core-functionality' );
	}

	/**
	 * Keeps the folder from being requested directly (the templates are only included by PHP).
	 *
	 * @since  1.0.0
	 * @param  string $dir The folder that holds the CF templates and the previous version.
	 * @return void
	 */
	private function protect( string $dir ): void {
		global $wp_filesystem;

		wp_mkdir_p( $dir );
		if ( ! file_exists( $dir . '/index.php' ) ) {
			$wp_filesystem->put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			$wp_filesystem->put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" );
		}
	}

	/**
	 * After a change: lets PHP read the templates anew and the template check scan again.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function after_change(): void {
		delete_transient( TemplateMonitor::TRANSIENT );

		if ( ! function_exists( 'opcache_invalidate' ) ) {
			return;
		}
		foreach ( self::templates_in( TemplateLocations::cf_dir() ) as $file ) {
			opcache_invalidate( $file, true );
		}
	}

	/**
	 * Shows an error and reports failure.
	 *
	 * @since  1.0.0
	 * @param  string $message The error.
	 * @return false
	 */
	private function fail( string $message ): bool {
		$this->plugin->notices->add( 'error', $message );

		return false;
	}

	/**
	 * Returns the button in the bar under the form: saving the options. Upload, download and
	 * restore stay with their sections.
	 *
	 * @since  1.0.0
	 * @return array The action buttons.
	 */
	#[Override]
	public function get_action_buttons(): array {
		return array(
			'save' => $this->button( 'save-options', __( 'Save', 'core-functionality' ), '', 'button button-primary button-large' ),
		);
	}
}
