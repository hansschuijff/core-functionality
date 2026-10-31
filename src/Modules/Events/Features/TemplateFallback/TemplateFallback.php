<?php
/**
 * Event Template Fallback Feature.
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

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;

use function __;
use function add_action;
use function add_filter;
use function file_exists;
use function get_stylesheet_directory;
use function is_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;
use function wp_normalize_path;

/**
 * Class TemplateFallback
 *
 * Template overrides of The Events Calendar and Event Tickets kept for this site, apart from the
 * theme, in wp-content/core-functionality/templates/ (see TemplateLocations::cf_dir()). They come
 * after the themes and before the plugins' own templates:
 *
 *   1. child theme   2. parent theme   3. CF templates   4. the plugin's original
 *
 * so a theme can still override a template when it needs its own, and a theme without overrides
 * gets these. The folders mirror a theme's: tribe/<plugin>/… for the current views and
 * tribe-events/… for the legacy (v1) ones. The folder is replaced as a whole from a zip on the
 * settings tab (CfTemplatesSettings), never by a plugin release.
 *
 * @since 1.0.0
 */
class TemplateFallback extends AbstractFeature {

	/**
	 * The lookup priority: child theme 10, parent theme 15, plugin 20.
	 *
	 * @var int
	 */
	private const PRIORITY = 16;

	/**
	 * Whether this feature runs in this request, so the template check knows to include its folder.
	 *
	 * @var bool
	 */
	private static bool $running = false;

	/**
	 * Tells whether this feature runs (its folder is part of the template lookup).
	 *
	 * @since  1.0.0
	 * @return bool True when it runs.
	 */
	public static function is_running(): bool {
		return self::$running;
	}

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'events_template_fallback',
			name:        __( 'Own templates', 'core-functionality' ),
			description: __( 'Template overrides of The Events Calendar and Event Tickets kept for this site apart from the theme, used when the theme has no override of its own. Upload and download them as a zip on the settings page.', 'core-functionality' ),
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Adds the CF templates folder to both template lookups, and registers its settings tab.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		self::$running = true;
		$dir           = TemplateLocations::cf_dir();

		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( CfTemplatesSettings::class );
			}
		);

		add_filter(
			'tribe_template_theme_path_list',
			fn ( $folders ) => $this->add_folder( (array) $folders, $dir ),
			10,
			1
		);
		add_filter(
			'tribe_events_template',
			fn ( $file, $template ) => $this->legacy_template( $file, $template, $dir ),
			10,
			2
		);
	}

	/**
	 * Current views: adds a folder after the themes. Its path is the child theme's path for this
	 * template with the theme folder swapped for the CF templates folder, so it also holds sub
	 * folders such as v2/ ("templates/tribe/events/v2").
	 *
	 * @since  1.0.0
	 * @param  array  $folders The theme folders The Events Calendar looks in.
	 * @param  string $dir     The CF templates folder.
	 * @return array The folders.
	 */
	private function add_folder( array $folders, string $dir ): array {
		$child_path = is_array( $folders['child-theme'] ?? null ) && is_string( $folders['child-theme']['path'] ?? null )
			? wp_normalize_path( $folders['child-theme']['path'] )
			: '';
		$theme_dir  = wp_normalize_path( (string) get_stylesheet_directory() );

		if ( '' === $child_path || ! str_starts_with( $child_path, $theme_dir ) ) {
			return $folders;
		}

		$folders['core-functionality'] = array(
			'id'       => 'core-functionality',
			'priority' => self::PRIORITY,
			'path'     => $dir . substr( $child_path, strlen( $theme_dir ) ),
		);

		return $folders;
	}

	/**
	 * Legacy (v1) views look in the theme first and then in the plugin's src/views/. A file of
	 * the plugin itself is swapped for the CF version when there is one; a theme's file
	 * is never touched, so a theme still overrides.
	 *
	 * @since  1.0.0
	 * @param  mixed  $file     The file found (a theme's or a plugin's), or false.
	 * @param  mixed  $template The template asked for, such as "modules/address.php".
	 * @param  string $dir      The CF templates folder.
	 * @return mixed The file.
	 */
	private function legacy_template( mixed $file, mixed $template, string $dir ): mixed {
		if ( ! is_string( $file ) || ! is_string( $template ) || ! str_starts_with( wp_normalize_path( $file ), wp_normalize_path( WP_PLUGIN_DIR ) ) ) {
			return $file;
		}

		$own = $dir . '/tribe-events/' . $template;

		return file_exists( $own ) ? $own : $file;
	}
}
