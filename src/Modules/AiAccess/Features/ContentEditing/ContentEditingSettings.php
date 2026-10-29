<?php
/**
 * AI Access Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Environment\Registers\PostTypeRegister;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\AiAccess;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\ContentTypeProfileInterface;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;

use function __;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_post_type_object;
use function implode;
use function is_array;
use function is_string;
use function sanitize_key;
use function wp_unslash;

/**
 * Class ContentEditingSettings
 *
 * The AI Access settings tab: per content type whether an AI assistant may use it, and whether
 * it may change published items of that type in a Rewrite & Republish copy. A type without a
 * saved choice follows its profile's defaults.
 *
 * @since 1.0.0
 */
class ContentEditingSettings implements SettingsSectionInterface {

	/**
	 * The settings key of all choices of this tab.
	 *
	 * @var string
	 */
	public const SETTINGS_KEY = 'ai-access-settings';

	/**
	 * The name of the form field array.
	 *
	 * @var string
	 */
	private const FIELD = 'dwp_cf_ai';

	/**
	 * Constructor.
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
		return 'cf-ai-access-settings';
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-superhero-alt';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'AI Access', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'What an AI assistant (for example Claude through the MCP Adapter) may do with the content of this site. It works with the rights of the user it logs in as, writes drafts only and never publishes or deletes.', 'core-functionality' );
	}

	/**
	 * Returns the stylesheets this tab needs (none).
	 *
	 * @since  1.0.0
	 * @return array Empty array.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array();
	}

	/**
	 * Returns the scripts this tab needs (none).
	 *
	 * @since  1.0.0
	 * @return array Empty array.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array();
	}

	/**
	 * Returns the saved choices, completed with the defaults and cleaned up.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin        The main plugin instance.
	 * @param  bool   $defaults_only Whether to return the defaults instead of the saved choices.
	 * @return array{types: array<string, array{enabled?: bool, rewrite?: bool}>} The choices.
	 */
	public static function get_values( Plugin $plugin, bool $defaults_only = false ): array {
		$module   = AiAccess::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );

		return self::normalize( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array{types: array<string, array{read?: bool, write?: bool, rewrite?: bool}>} The choices.
	 */
	private static function normalize( array $values ): array {
		$types = array();
		foreach ( is_array( $values['types'] ?? null ) ? $values['types'] : array() as $post_type => $choices ) {
			if ( is_string( $post_type ) && is_array( $choices ) ) {
				$types[ sanitize_key( $post_type ) ] = self::normalize_levels( $choices );
			}
		}

		return array( 'types' => $types );
	}

	/**
	 * Returns the levels of one content type. A level that is on includes the lower ones. The
	 * former single choice "enabled" (before October 2026) stands for reading and drafts.
	 *
	 * @since  1.0.0
	 * @param  array $choices The raw choices of the type.
	 * @return array<string, bool> Level => on, only for the levels that were chosen.
	 */
	private static function normalize_levels( array $choices ): array {
		$levels = array();
		if ( isset( $choices['enabled'] ) ) {
			$levels[ ContentTypes::LEVEL_READ ]  = ! empty( $choices['enabled'] );
			$levels[ ContentTypes::LEVEL_WRITE ] = ! empty( $choices['enabled'] );
		}

		foreach ( ContentTypes::LEVELS as $level ) {
			if ( isset( $choices[ $level ] ) ) {
				$levels[ $level ] = ! empty( $choices[ $level ] );
			}
		}

		// A higher level that is on switches the lower ones on.
		if ( ! empty( $levels[ ContentTypes::LEVEL_REWRITE ] ) ) {
			$levels[ ContentTypes::LEVEL_WRITE ] = true;
		}
		if ( ! empty( $levels[ ContentTypes::LEVEL_WRITE ] ) ) {
			$levels[ ContentTypes::LEVEL_READ ] = true;
		}

		return $levels;
	}

	/**
	 * Outputs the tab: one row per content type.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$types   = new ContentTypes( $this->plugin );
		$choices = self::get_values( $this->plugin, $defaults_only )['types'];

		$rows = array();
		foreach ( $this->group_by_plugin( $types->get_all() ) as $plugin_name => $profiles ) {
			$rows[] = $this->render_group_row( $plugin_name );
			foreach ( $profiles as $post_type => $profile ) {
				$rows[] = $this->render_type_row( $profile, $choices[ $post_type ] ?? array() );
			}
		}

		$heading_type    = esc_html__( 'Content type', 'core-functionality' );
		$heading_read    = esc_html__( 'Read', 'core-functionality' );
		$heading_write   = esc_html__( 'Make and change drafts', 'core-functionality' );
		$heading_rewrite = esc_html__( 'Change published content', 'core-functionality' );

		echo '<table class="widefat striped dwp-cf-ai-levels" role="presentation"><thead><tr>';
		echo '<th scope="col">' . $heading_type . '</th><th scope="col">' . $heading_read . '</th><th scope="col">' . $heading_write . '</th><th scope="col">' . $heading_rewrite . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '</tr></thead><tbody>' . implode( '', $rows ) . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Groups the profiles by the plugin their post type belongs to, as the post type catalogue knows
	 * it; post types of no known plugin come last, under "Other".
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface[] $profiles The profiles, keyed by post type.
	 * @return array<string, ContentTypeProfileInterface[]> Plugin name => profiles.
	 */
	private function group_by_plugin( array $profiles ): array {
		$groups = array();
		$other  = array();

		foreach ( $profiles as $post_type => $profile ) {
			$provider_id = PostTypeRegister::get_provider( $post_type );
			if ( null === $provider_id ) {
				$other[ $post_type ] = $profile;
				continue;
			}

			$groups[ PostTypeRegister::get_name( $provider_id ) ][ $post_type ] = $profile;
		}

		if ( array() !== $other ) {
			$groups[ __( 'Other', 'core-functionality' ) ] = $other;
		}

		return $groups;
	}

	/**
	 * Returns the heading row of a plugin's group.
	 *
	 * @since  1.0.0
	 * @param  string $plugin_name The plugin's name.
	 * @return string The row.
	 */
	private function render_group_row( string $plugin_name ): string {
		$name = esc_html( $plugin_name );

		return "<tr><th scope=\"rowgroup\" colspan=\"4\"><strong>{$name}</strong></th></tr>";
	}

	/**
	 * Returns the row of one content type.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  array                       $saved   The saved levels of this type.
	 * @return string The row.
	 */
	private function render_type_row( ContentTypeProfileInterface $profile, array $saved ): string {
		$post_type = $profile->get_post_type();
		$object    = get_post_type_object( $post_type );
		$name      = null !== $object ? $object->labels->name : $post_type;
		$label     = esc_html( $name );
		$code      = esc_html( $post_type );

		$cells = '';
		foreach ( ContentTypes::LEVELS as $level ) {
			$default = ContentTypes::LEVEL_REWRITE === $level ? $profile->allows_rewrite_by_default() : true;
			$cells  .= '<td>' . $this->checkbox( $post_type, $level, $saved[ $level ] ?? $default, $name ) . '</td>';
		}

		return "<tr><td>{$label} <code>{$code}</code></td>{$cells}</tr>";
	}

	/**
	 * Returns a checkbox with a hidden field that sends "0" when it is unchecked. The data
	 * attributes let the tab's script keep the levels of a row in steps.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @param  string $level     One of the ContentTypes::LEVEL_ constants.
	 * @param  bool   $checked   Whether it is checked.
	 * @param  string $label     The content type's name (for screen readers).
	 * @return string The checkbox.
	 */
	private function checkbox( string $post_type, string $level, bool $checked, string $label ): string {
		$name       = esc_attr( self::FIELD . '[types][' . $post_type . '][' . $level . ']' );
		$type_attr  = esc_attr( $post_type );
		$level_attr = esc_attr( $level );
		$aria       = esc_attr( $label . ': ' . $this->get_level_name( $level ) );

		return '<input type="hidden" name="' . $name . '" value="0" />'
			. '<input type="checkbox" name="' . $name . '" value="1" data-type="' . $type_attr . '" data-level="' . $level_attr . '" aria-label="' . $aria . '"' . checked( $checked, true, false ) . ' />';
	}

	/**
	 * Returns the name of a level, as in the column heading.
	 *
	 * @since  1.0.0
	 * @param  string $level One of the ContentTypes::LEVEL_ constants.
	 * @return string The name.
	 */
	private function get_level_name( string $level ): string {
		return match ( $level ) {
			ContentTypes::LEVEL_READ  => __( 'Read', 'core-functionality' ),
			ContentTypes::LEVEL_WRITE => __( 'Make and change drafts', 'core-functionality' ),
			default                   => __( 'Change published content', 'core-functionality' ),
		};
	}

	/**
	 * Validates the submitted form data before it is saved (nothing to check: only checkboxes).
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return bool|WP_Error True.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		return true;
	}

	/**
	 * Saves the submitted form data, as a whole.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return string|bool A success message, true, or false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		$input = $posted_data[ self::FIELD ] ?? array();
		$input = is_array( $input ) ? wp_unslash( $input ) : array();

		return $this->plugin->settings->save( self::SETTINGS_KEY, self::normalize( $input ) );
	}

	/**
	 * Returns the button under the form of this tab.
	 *
	 * @since  1.0.0
	 * @return array The action buttons.
	 */
	#[Override]
	public function get_action_buttons(): array {
		$label = esc_html__( 'Save', 'core-functionality' );

		return array(
			'save' => '<button type="submit" name="dwp_vault_matrix_submit" value="1" class="button button-primary button-large">' . $label . '</button>',
		);
	}
}
