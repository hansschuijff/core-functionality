<?php
/**
 * Event Template Monitor Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Events\Features\TemplateMonitor
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateMonitor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateFallback\CfTemplatesSettings;
use DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateFallback\TemplateFallback;
use DeWittePrins\CoreFunctionality\Modules\Events\Features\TemplateFallback\TemplateLocations;
use Override;
use WP_Screen;

use const HOUR_IN_SECONDS;

use function __;
use function add_action;
use function add_query_arg;
use function array_fill_keys;
use function array_filter;
use function array_keys;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function fclose;
use function file_exists;
use function fopen;
use function fread;
use function get_transient;
use function in_array;
use function is_admin;
use function is_array;
use function is_readable;
use function md5_file;
use function preg_match;
use function remove_query_arg;
use function sanitize_key;
use function set_transient;
use function sprintf;
use function str_contains;
use function version_compare;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Class TemplateMonitor
 *
 * Warns administrators when a template override of The Events Calendar or Event Tickets is out of
 * date, or overrides a template the plugin no longer has. Per template it checks the version the
 * site actually uses, found as The Events Calendar finds it (child theme, parent theme, then the
 * CF templates folder), against the plugin's original.
 *
 * Also reported, as less urgent: a CF template that is out of date while a theme overrides it
 * (it is used again after a theme switch), and a theme copy identical to the CF template (it
 * can go). A template marked @custom-template has no original by design.
 *
 * Shown, and scanned for, only on the dashboard, the plugins and updates pages and the event
 * templates tab, to users who manage plugins; it can be closed for the page view. When only theme
 * copies that can go are left, it is informational instead of a warning. The result is kept for 12 hours and
 * renewed after updates, a theme switch, a CF templates upload, or the notice's rescan button.
 *
 * @since 1.0.0
 */
class TemplateMonitor extends AbstractFeature {

	/**
	 * The transient holding the last result.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'dwp_cf_event_template_report';

	/**
	 * The query arg and nonce action of the rescan button.
	 *
	 * @var string
	 */
	private const RESCAN = 'dwp_cf_rescan_event_templates';

	/**
	 * The admin pages that show the notice (besides the event templates tab), single site and network.
	 *
	 * @var string[]
	 */
	private const SCREENS = array( 'dashboard', 'plugins', 'update-core', 'dashboard-network', 'plugins-network', 'update-core-network' );

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'events_template_monitor',
			name:        __( 'Event template check', 'core-functionality' ),
			description: __( 'Warns when a template override of The Events Calendar or Event Tickets is out of date or overrides a template the plugin no longer has.', 'core-functionality' ),
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Hooks the check into the admin, and clears the result after updates and theme switches.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action( 'upgrader_process_complete', array( $this, 'clear' ) );
		add_action( 'after_switch_theme', array( $this, 'clear' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'current_screen', array( $this, 'check' ) );
	}

	/**
	 * Whether the notice belongs on this admin page: the dashboard, the plugins and updates pages,
	 * and the event templates tab. Other pages neither scan nor show it.
	 *
	 * @since  1.0.0
	 * @param  WP_Screen $screen The current admin page.
	 * @return bool True on those pages.
	 */
	private function is_notice_page( WP_Screen $screen ): bool {
		if ( in_array( $screen->id, self::SCREENS, true ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the page.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		return 'settings_page_' . AdminSettingsPage::SLUG === $screen->id && CfTemplatesSettings::get_id() === $section;
	}

	/**
	 * Forgets the last result, so the next admin page scans again.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Handles the rescan button, gets the (kept) result and shows it when there is anything to report.
	 *
	 * @since  1.0.0
	 * @param  WP_Screen $screen The current admin page.
	 * @return void
	 */
	public function check( WP_Screen $screen ): void {
		if ( ! current_user_can( 'activate_plugins' ) || ! $this->is_notice_page( $screen ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below.
		if ( isset( $_GET[ self::RESCAN ], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::RESCAN ) ) {
			$this->clear();
			wp_safe_redirect( remove_query_arg( array( self::RESCAN, '_wpnonce' ) ) );
			exit;
		}

		$report = get_transient( self::TRANSIENT );
		if ( ! is_array( $report ) ) {
			$report = $this->scan();
			set_transient( self::TRANSIENT, $report, 12 * HOUR_IN_SECONDS );
		}

		// Theme copies of CF templates are left out when the tab says so; the scan keeps them.
		if ( ! CfTemplatesSettings::reports_theme_copies( $this->plugin ) ) {
			$report = array_filter( $report, fn ( $finding ) => 'redundant' !== $finding['type'] );
		}

		if ( array() === $report ) {
			return;
		}

		add_action( 'admin_notices', fn () => $this->render_notice( $report ) );
	}

	/**
	 * Scans the override folders and returns what to report.
	 *
	 * @since  1.0.0
	 * @return array<int, array<string, string>> The findings: type, source, path and versions.
	 */
	private function scan(): array {
		$copies = TemplateLocations::collect( TemplateFallback::is_running() );
		$report = array();

		foreach ( $copies as $path => $found ) {
			$used     = $found[0];
			$original = $used['views'] . $used['relative'];

			// What the site uses: missing in the plugin, or older than the plugin's.
			if ( ! file_exists( $original ) ) {
				if ( ! $this->is_custom( $used['file'] ) ) {
					$report[] = $this->finding( 'missing', $used['source'], $path );
				}
			} elseif ( $this->is_older( $used['file'], $original ) ) {
				$report[] = $this->finding( 'outdated', $used['source'], $path, $this->version( $used['file'] ), $this->version( $original ) );
			}

			$cf = $this->find_source( $found, 'cf' );
			if ( null === $cf || 'cf' === $used['source'] ) {
				continue;
			}

			// A CF template a theme overrides now, but used again after a theme switch.
			if ( file_exists( $original ) && $this->is_older( $cf['file'], $original ) ) {
				$report[] = $this->finding( 'cf-outdated', 'cf', $path, $this->version( $cf['file'] ), $this->version( $original ) );
			}

			// Theme copies identical to the CF template can go.
			foreach ( $found as $copy ) {
				if ( 'cf' !== $copy['source'] && md5_file( $copy['file'] ) === md5_file( $cf['file'] ) ) {
					$report[] = $this->finding( 'redundant', $copy['source'], $path );
				}
			}
		}

		return $report;
	}

	/**
	 * Returns the copy from one source, if there is one.
	 *
	 * @since  1.0.0
	 * @param  array  $found  The copies of one template.
	 * @param  string $source The source.
	 * @return array|null The copy.
	 */
	private function find_source( array $found, string $source ): ?array {
		foreach ( $found as $copy ) {
			if ( $source === $copy['source'] ) {
				return $copy;
			}
		}

		return null;
	}

	/**
	 * Whether a template has an older @version than the original.
	 *
	 * @since  1.0.0
	 * @param  string $file     The override.
	 * @param  string $original The plugin's template.
	 * @return bool True when both have a version and the override's is lower.
	 */
	private function is_older( string $file, string $original ): bool {
		$version  = $this->version( $file );
		$original = $this->version( $original );

		return '' !== $version && '' !== $original && version_compare( $version, $original, '<' );
	}

	/**
	 * Returns the @version of a template, from its first 2 kB.
	 *
	 * @since  1.0.0
	 * @param  string $file The template.
	 * @return string The version, or ''.
	 */
	private function version( string $file ): string {
		return 1 === preg_match( '/@version\s+([0-9.]+)/i', $this->head( $file ), $matches ) ? $matches[1] : '';
	}

	/**
	 * Whether a template is marked as having no original (@custom-template).
	 *
	 * @since  1.0.0
	 * @param  string $file The template.
	 * @return bool True when marked.
	 */
	private function is_custom( string $file ): bool {
		return str_contains( $this->head( $file ), '@custom-template' );
	}

	/**
	 * Reads the first 2 kB of a file, where the header is.
	 *
	 * @since  1.0.0
	 * @param  string $file The file.
	 * @return string The start of the file, or ''.
	 */
	private function head( string $file ): string {
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Only the header, not the whole file.
		if ( false === $handle ) {
			return '';
		}
		$head = (string) fread( $handle, 2048 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $head;
	}

	/**
	 * Builds one finding.
	 *
	 * @since  1.0.0
	 * @param  string $type     missing, outdated, cf-outdated or redundant.
	 * @param  string $source   child, parent or cf.
	 * @param  string $path     The template path, such as tribe/events/v2/list/event/venue.php.
	 * @param  string $version  The override's version, or ''.
	 * @param  string $original The plugin's version, or ''.
	 * @return array<string, string> The finding.
	 */
	private function finding( string $type, string $source, string $path, string $version = '', string $original = '' ): array {
		return array(
			'type'     => $type,
			'source'   => $source,
			'path'     => $path,
			'version'  => $version,
			'original' => $original,
		);
	}

	/**
	 * Shows the findings grouped by kind, each group a heading with its templates below it: what
	 * the site uses first, then what matters after a theme switch, then theme copies that can go.
	 *
	 * @since  1.0.0
	 * @param  array $report The findings.
	 * @return void
	 */
	private function render_notice( array $report ): void {
		$rescan = wp_nonce_url( add_query_arg( self::RESCAN, '1' ), self::RESCAN );

		// Theme copies that can go ask no action; only then the notice is informational.
		$type = array() === array_filter( $report, fn ( $finding ) => 'redundant' !== $finding['type'] ) ? 'info' : 'warning';

		// Dismissible for this page view only: it returns on the next one.
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p><strong>' . esc_html__( 'Event templates', 'core-functionality' ) . '</strong> ';
		echo '<a class="button button-small" href="' . esc_url( $rescan ) . '">' . esc_html__( 'Scan again', 'core-functionality' ) . '</a></p>';

		foreach ( $this->group( $report ) as $heading => $items ) {
			echo '<p>' . esc_html( $heading ) . '</p><ul style="list-style: disc; margin-left: 2em;">';
			foreach ( $items as $item ) {
				echo '<li><code>' . esc_html( $item ) . '</code></li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Sorts the findings into groups, in the order they are shown.
	 *
	 * @since  1.0.0
	 * @param  array $report The findings.
	 * @return array<string, string[]> Heading => one line per template.
	 */
	private function group( array $report ): array {
		$sources  = array(
			'child'  => __( 'child theme', 'core-functionality' ),
			'parent' => __( 'parent theme', 'core-functionality' ),
			'cf'     => __( 'CF template', 'core-functionality' ),
		);
		$headings = array(
			'outdated'         => __( 'These templates are out of date:', 'core-functionality' ),
			'missing'          => __( 'These templates override one the plugin no longer has (moved or removed):', 'core-functionality' ),
			'cf-outdated'      => __( 'These CF templates are out of date. A theme overrides them now, but they are used again after a theme switch:', 'core-functionality' ),
			'redundant-child'  => __( 'These templates in the child theme are the same as the CF templates and can be removed:', 'core-functionality' ),
			'redundant-parent' => __( 'These templates in the parent theme are the same as the CF templates and can be removed:', 'core-functionality' ),
		);

		$groups = array_fill_keys( array_keys( $headings ), array() );
		foreach ( $report as $finding ) {
			$where = $sources[ $finding['source'] ] ?? $finding['source'];
			$key   = 'redundant' === $finding['type'] ? 'redundant-' . $finding['source'] : $finding['type'];

			// The path first, then whose copy it is, right next to its version.
			$groups[ $key ][] = match ( $finding['type'] ) {
				/* translators: 1: template path, 2: whose copy it is (child theme, parent theme, CF template), 3: its version, 4: the plugin's version. */
				'outdated', 'cf-outdated' => sprintf( __( '%1$s: %2$s has v%3$s, the plugin has v%4$s', 'core-functionality' ), $finding['path'], $where, $finding['version'], $finding['original'] ),
				/* translators: 1: template path, 2: whose copy it is (child theme, parent theme, CF template). */
				'missing'                   => sprintf( __( '%1$s: %2$s has it, the plugin no longer does', 'core-functionality' ), $finding['path'], $where ),
				default                     => $finding['path'],
			};
		}

		$grouped = array();
		foreach ( array_filter( $groups ) as $key => $items ) {
			$grouped[ $headings[ $key ] ] = $items;
		}

		return $grouped;
	}
}
