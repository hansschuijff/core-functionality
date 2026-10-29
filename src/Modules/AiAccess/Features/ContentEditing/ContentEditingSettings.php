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
	 * @return array{types: array<string, array{enabled?: bool, rewrite?: bool}>} The choices.
	 */
	private static function normalize( array $values ): array {
		$types = array();
		foreach ( is_array( $values['types'] ?? null ) ? $values['types'] : array() as $post_type => $choices ) {
			if ( ! is_string( $post_type ) || ! is_array( $choices ) ) {
				continue;
			}

			$post_type = sanitize_key( $post_type );
			foreach ( array( 'enabled', 'rewrite' ) as $choice ) {
				if ( isset( $choices[ $choice ] ) ) {
					$types[ $post_type ][ $choice ] = ! empty( $choices[ $choice ] );
				}
			}
		}

		return array( 'types' => $types );
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
		foreach ( $types->get_all() as $post_type => $profile ) {
			$rows[] = $this->render_type_row( $profile, $choices[ $post_type ] ?? array() );
		}

		$heading_type    = esc_html__( 'Content type', 'core-functionality' );
		$heading_enabled = esc_html__( 'AI may use it', 'core-functionality' );
		$heading_rewrite = esc_html__( 'Change published items in a copy', 'core-functionality' );
		$hint            = esc_html__( 'A copy (Rewrite & Republish of Yoast Duplicate Post) replaces the original only when you publish it. Switch it on per type only after testing that the type\'s own data (tickets, dates, course structure) survives.', 'core-functionality' );

		echo '<table class="widefat striped" role="presentation"><thead><tr>';
		echo '<th scope="col">' . $heading_type . '</th><th scope="col">' . $heading_enabled . '</th><th scope="col">' . $heading_rewrite . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '</tr></thead><tbody>' . implode( '', $rows ) . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo '<p class="description">' . $hint . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}

	/**
	 * Returns the row of one content type.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile.
	 * @param  array                       $saved   The saved choices of this type.
	 * @return string The row.
	 */
	private function render_type_row( ContentTypeProfileInterface $profile, array $saved ): string {
		$post_type = $profile->get_post_type();
		$object    = get_post_type_object( $post_type );
		$label     = esc_html( null !== $object ? $object->labels->name : $post_type );
		$code      = esc_html( $post_type );

		$enabled = $saved['enabled'] ?? true;
		$rewrite = $saved['rewrite'] ?? $profile->allows_rewrite_by_default();

		$enabled_box = $this->checkbox( $post_type, 'enabled', $enabled );
		$rewrite_box = $this->checkbox( $post_type, 'rewrite', $rewrite );

		return "<tr><td>{$label} <code>{$code}</code></td><td>{$enabled_box}</td><td>{$rewrite_box}</td></tr>";
	}

	/**
	 * Returns a checkbox with a hidden field that sends "0" when it is unchecked.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @param  string $choice    'enabled' or 'rewrite'.
	 * @param  bool   $checked   Whether it is checked.
	 * @return string The checkbox.
	 */
	private function checkbox( string $post_type, string $choice, bool $checked ): string {
		$name = esc_attr( self::FIELD . '[types][' . $post_type . '][' . $choice . ']' );

		return '<input type="hidden" name="' . $name . '" value="0" /><input type="checkbox" name="' . $name . '" value="1"' . checked( $checked, true, false ) . ' />';
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
