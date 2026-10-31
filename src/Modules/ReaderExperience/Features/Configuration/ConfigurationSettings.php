<?php
/**
 * Reader Experience Settings Tab.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\ReaderExperience\Features\Configuration
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\ReaderExperience\Features\Configuration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Modules\ReaderExperience\ReaderExperience;
use DeWittePrins\CoreFunctionality\Plugin;
use Override;
use WP_Error;

use function __;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function checked;
use function constant;
use function defined;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function explode;
use function get_post_types;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function max;
use function min;
use function plugins_url;
use function preg_match;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_ends_with;
use function trim;
use function wp_unslash;

/**
 * Class ConfigurationSettings
 *
 * The settings tab of the Reader Experience plugin: heading anchors, reading time and feedback.
 * The choices are saved as a whole (a post type unchecked stays unchecked); until the first save
 * the defaults file in settings/modules/reader-experience/ applies.
 *
 * @since 1.0.0
 */
class ConfigurationSettings implements SettingsSectionInterface {

	/**
	 * The settings key of all choices of this tab.
	 *
	 * @var string
	 */
	public const SETTINGS_KEY = 'reader-experience-settings';

	/**
	 * The name of the form field array.
	 *
	 * @var string
	 */
	private const FIELD = 'dwp_rx';

	/**
	 * The words per minute the plugin itself assumes, and the range a value is kept in.
	 *
	 * @var int[]
	 */
	private const WPM = array(
		'default' => 225,
		'min'     => 50,
		'max'     => 1000,
	);

	/**
	 * ConfigurationSettings Constructor.
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
		return 'cf-reader-experience-settings';
	}

	/**
	 * Returns the dashicon class shown next to the tab title.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-book-alt';
	}

	/**
	 * Returns the title of this settings tab.
	 *
	 * @since  1.0.0
	 * @return string The tab title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Settings', 'core-functionality' );
	}

	/**
	 * Returns the description shown at the top of this tab.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Settings of the Reader Experience plugin on this site. Code on a later filter priority (a theme or mu-plugin) can still override them, for example per post.', 'core-functionality' );
	}

	/**
	 * Returns the stylesheets this tab needs.
	 *
	 * @since  1.0.0
	 * @return array The stylesheets to enqueue.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array(
			'dwp-cf-reader-experience-settings-css' => plugins_url( 'assets/css/configuration-settings.css', __FILE__ ),
		);
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
	 * Returns the saved choices, completed with the defaults and cleaned up, so each key is there
	 * with a value of the right type. Used by the tab and by the feature that applies them.
	 *
	 * @since  1.0.0
	 * @param  Plugin $plugin        The main plugin instance.
	 * @param  bool   $defaults_only Whether to return the defaults instead of the saved choices.
	 * @return array The choices.
	 */
	public static function get_values( Plugin $plugin, bool $defaults_only = false ): array {
		$module   = ReaderExperience::get_id();
		$defaults = $plugin->settings->get_default( self::SETTINGS_KEY, $module );
		$defaults = is_array( $defaults ) ? $defaults : array();
		$saved    = $defaults_only ? $defaults : $plugin->settings->get_whole( self::SETTINGS_KEY, $module, $defaults );

		return self::normalize( ( is_array( $saved ) ? $saved : array() ) + $defaults );
	}

	/**
	 * Gives every choice a value of the right type.
	 *
	 * @since  1.0.0
	 * @param  array $values The raw choices.
	 * @return array The choices.
	 */
	private static function normalize( array $values ): array {
		$wpm = is_numeric( $values['reading_wpm'] ?? null ) ? (int) $values['reading_wpm'] : self::WPM['default'];

		return array(
			'anchors_enabled'      => ! empty( $values['anchors_enabled'] ),
			'anchors_post_types'   => self::list_of( $values['anchors_post_types'] ?? array(), 'sanitize_key' ),
			'anchors_position'     => 'after' === ( $values['anchors_position'] ?? '' ) ? 'after' : 'before',
			'anchors_containers'   => self::list_of( $values['anchors_containers'] ?? array(), array( self::class, 'clean_selector' ) ),
			'anchors_label_copy'   => sanitize_text_field( (string) ( $values['anchors_label_copy'] ?? '' ) ),
			'anchors_label_copied' => sanitize_text_field( (string) ( $values['anchors_label_copied'] ?? '' ) ),
			'anchors_load_css'     => ! empty( $values['anchors_load_css'] ),
			'anchors_load_js'      => ! empty( $values['anchors_load_js'] ),
			'anchors_reserved_ids' => self::list_of( $values['anchors_reserved_ids'] ?? array(), 'sanitize_html_class' ),
			'reading_wpm'          => max( self::WPM['min'], min( self::WPM['max'], $wpm ) ),
			'feedback_notify'      => ! empty( $values['feedback_notify'] ),
			'feedback_spam_check'  => ! empty( $values['feedback_spam_check'] ),
		);
	}

	/**
	 * Turns a list (or text with one item per line) into clean, unique, non-empty strings.
	 *
	 * @since  1.0.0
	 * @param  mixed    $items    An array, or text with one item per line.
	 * @param  callable $sanitize Cleans one item.
	 * @return string[] The items.
	 */
	private static function list_of( mixed $items, callable $sanitize ): array {
		$items = is_array( $items ) ? $items : explode( "\n", (string) $items );

		return array_values( array_unique( array_filter( array_map( fn ( $item ) => $sanitize( trim( (string) $item ) ), $items ) ) ) );
	}

	/**
	 * Keeps a CSS selector only when it holds no characters that could break out of it.
	 *
	 * @since  1.0.0
	 * @param  string $selector A CSS selector.
	 * @return string The selector, or '' when it is refused.
	 */
	private static function clean_selector( string $selector ): string {
		$selector = sanitize_text_field( $selector );

		return 1 === preg_match( '/^[\w\s.#>+~:()\[\]="\'*,-]+$/u', $selector ) ? $selector : '';
	}

	/**
	 * Outputs the tab.
	 *
	 * @since  1.0.0
	 * @param  bool $defaults_only Whether to show the defaults instead of the saved choices.
	 * @return void
	 */
	#[Override]
	public function render( bool $defaults_only = false ): void {
		$values = self::get_values( $this->plugin, $defaults_only );

		echo '<div class="dwp-rx-settings">';
		$this->render_anchors( $values );
		$this->render_reading( $values );
		$this->render_feedback( $values );
		echo '</div>';
	}

	/**
	 * Outputs the heading anchor settings.
	 *
	 * @since  1.0.0
	 * @param  array $values The choices.
	 * @return void
	 */
	private function render_anchors( array $values ): void {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$post_types[] = $this->checkbox( 'anchors_post_types][', $type->labels->name, in_array( $type->name, $values['anchors_post_types'], true ), $type->name );
			}
		}

		// The plugin's own (translated) defaults, shown where a field is left empty.
		$copy_default   = __( 'Copy link to this section', 'reader-experience' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- The other plugin's own default.
		$copied_default = __( 'Link copied', 'reader-experience' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- The other plugin's own default.

		$this->fieldset(
			__( 'Heading anchors', 'core-functionality' ),
			__( 'Gives headings an id and a button that copies a link to that section.', 'core-functionality' ),
			array(
				$this->row( __( 'Anchor links', 'core-functionality' ), $this->checkbox( 'anchors_enabled', __( 'Add heading anchors', 'core-functionality' ), $values['anchors_enabled'] ) ),
				$this->row( __( 'Post types', 'core-functionality' ), '<div class="dwp-rx-choices">' . implode( '', $post_types ) . '</div>' ),
				$this->row(
					__( 'Button', 'core-functionality' ),
					'<div class="dwp-rx-choices">'
						. $this->radio( 'anchors_position', 'before', __( 'Before the heading text', 'core-functionality' ), $values['anchors_position'] )
						. $this->radio( 'anchors_position', 'after', __( 'After the heading text', 'core-functionality' ), $values['anchors_position'] )
					. '</div>'
				),
				$this->row( __( 'Button text', 'core-functionality' ), $this->text( 'anchors_label_copy', $values['anchors_label_copy'], $copy_default ) ),
				$this->row( __( 'Text after copying', 'core-functionality' ), $this->text( 'anchors_label_copied', $values['anchors_label_copied'], $copied_default ) ),
				$this->row(
					__( 'Containers', 'core-functionality' ),
					$this->lines( 'anchors_containers', $values['anchors_containers'], ".wp-block-post-content\n.entry-content\narticle" ),
					__( 'CSS selectors of the element that holds the headings, one per line, tried in order. Empty: the plugin\'s own list.', 'core-functionality' )
				),
				$this->row(
					__( 'Reserved ids', 'core-functionality' ),
					$this->lines( 'anchors_reserved_ids', $values['anchors_reserved_ids'], '' ),
					sprintf(
						/* translators: %s: the ids the plugin always reserves, separated by commas. */
						__( 'Ids a heading may not get because the theme or a plugin already uses them on the page, one per line. They come on top of the ids the plugin always reserves: %s.', 'core-functionality' ),
						implode( ', ', $this->get_plugin_reserved_ids() )
					)
				),
				$this->row(
					__( 'Assets', 'core-functionality' ),
					'<div class="dwp-rx-choices">'
						. $this->checkbox( 'anchors_load_css', __( 'Load the plugin\'s CSS', 'core-functionality' ), $values['anchors_load_css'] )
						. $this->checkbox( 'anchors_load_js', __( 'Load the plugin\'s JavaScript', 'core-functionality' ), $values['anchors_load_js'] )
					. '</div>',
					__( 'Switch one off when the theme brings its own styling or script.', 'core-functionality' )
				),
			)
		);
	}

	/**
	 * Returns the ids the Reader Experience plugin always reserves, from its own list.
	 *
	 * @since  1.0.0
	 * @return string[] The ids, or an empty list when that list cannot be found.
	 */
	private function get_plugin_reserved_ids(): array {
		$constant = 'ReaderExperience\Modules\Reading\AnchorRegistry::DEFAULT_RESERVED';

		return defined( $constant ) && is_array( constant( $constant ) ) ? constant( $constant ) : array();
	}

	/**
	 * Outputs the reading time settings.
	 *
	 * @since  1.0.0
	 * @param  array $values The choices.
	 * @return void
	 */
	private function render_reading( array $values ): void {
		$field = sprintf(
			'<input type="number" class="small-text" name="%1$s[reading_wpm]" value="%2$s" min="%3$s" max="%4$s" step="5" />',
			self::FIELD,
			esc_attr( (string) $values['reading_wpm'] ),
			esc_attr( (string) self::WPM['min'] ),
			esc_attr( (string) self::WPM['max'] )
		);

		$this->fieldset(
			__( 'Reading time', 'core-functionality' ),
			'',
			array(
				$this->row(
					__( 'Words per minute', 'core-functionality' ),
					$field,
					/* translators: %d: the plugin's default number of words per minute. */
					sprintf( __( 'The plugin assumes %d.', 'core-functionality' ), self::WPM['default'] )
				),
			)
		);
	}

	/**
	 * Outputs the feedback settings.
	 *
	 * @since  1.0.0
	 * @param  array $values The choices.
	 * @return void
	 */
	private function render_feedback( array $values ): void {
		$this->fieldset(
			__( 'Ratings and feedback', 'core-functionality' ),
			'',
			array(
				$this->row( __( 'Notification', 'core-functionality' ), $this->checkbox( 'feedback_notify', __( 'Email the site administrator about new feedback', 'core-functionality' ), $values['feedback_notify'] ) ),
				$this->row(
					__( 'Spam', 'core-functionality' ),
					$this->checkbox( 'feedback_spam_check', __( 'Check for many ratings in a short time (spam)', 'core-functionality' ), $values['feedback_spam_check'] )
				),
			)
		);
	}

	/**
	 * Outputs a group of settings.
	 *
	 * @since  1.0.0
	 * @param  string   $legend      The title of the group.
	 * @param  string   $description A line under the title, or ''.
	 * @param  string[] $rows        The rows (HTML).
	 * @return void
	 */
	private function fieldset( string $legend, string $description, array $rows ): void {
		echo '<fieldset class="dwp-rx-group"><legend>' . esc_html( $legend ) . '</legend>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '<table class="form-table" role="presentation">' . implode( '', $rows ) . '</table></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	/**
	 * Returns one row: a label, the field and an optional hint.
	 *
	 * @since  1.0.0
	 * @param  string $label The label, or '' for a row with a checkbox that labels itself.
	 * @param  string $field The field (HTML).
	 * @param  string $hint  A hint under the field, or ''.
	 * @return string The row.
	 */
	private function row( string $label, string $field, string $hint = '' ): string {
		$hint = '' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : '';

		return '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $field . $hint . '</td></tr>';
	}

	/**
	 * Returns a checkbox with its label. A hidden field sends "0" when it is unchecked; a list
	 * (a name ending in "][") has no such field.
	 *
	 * @since  1.0.0
	 * @param  string $name    The key in the form field array.
	 * @param  string $label   The label.
	 * @param  bool   $checked Whether it is checked.
	 * @param  string $value   The value sent when checked.
	 * @return string The checkbox.
	 */
	private function checkbox( string $name, string $label, bool $checked, string $value = '1' ): string {
		$field  = self::FIELD . '[' . $name . ']';
		$hidden = str_ends_with( $name, '][' ) ? '' : '<input type="hidden" name="' . esc_attr( $field ) . '" value="0" />';

		return $hidden . '<label class="dwp-rx-choice"><input type="checkbox" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '"' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * Returns a radio button with its label.
	 *
	 * @since  1.0.0
	 * @param  string $name    The key in the form field array.
	 * @param  string $value   The value of this option.
	 * @param  string $label   The label.
	 * @param  string $current The chosen value.
	 * @return string The radio button.
	 */
	private function radio( string $name, string $value, string $label, string $current ): string {
		return '<label class="dwp-rx-choice"><input type="radio" name="' . esc_attr( self::FIELD . '[' . $name . ']' ) . '" value="' . esc_attr( $value ) . '"' . checked( $current, $value, false ) . ' /> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * Returns a text field.
	 *
	 * @since  1.0.0
	 * @param  string $name        The key in the form field array.
	 * @param  string $value       The value.
	 * @param  string $placeholder What applies when it is left empty.
	 * @return string The field.
	 */
	private function text( string $name, string $value, string $placeholder ): string {
		return '<input type="text" class="regular-text" name="' . esc_attr( self::FIELD . '[' . $name . ']' ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	/**
	 * Returns a text area for a list, one item per line.
	 *
	 * @since  1.0.0
	 * @param  string   $name        The key in the form field array.
	 * @param  string[] $items       The items.
	 * @param  string   $placeholder What applies when it is left empty.
	 * @return string The text area.
	 */
	private function lines( string $name, array $items, string $placeholder ): string {
		return '<textarea class="large-text code" rows="4" name="' . esc_attr( self::FIELD . '[' . $name . ']' ) . '" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( implode( "\n", $items ) ) . '</textarea>';
	}

	/**
	 * Validates the submitted form data before it is saved.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The submitted form data.
	 * @return bool|WP_Error True when valid, an error otherwise.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		$wpm = $posted_data[ self::FIELD ]['reading_wpm'] ?? '';
		if ( ! is_numeric( $wpm ) || (int) $wpm < self::WPM['min'] || (int) $wpm > self::WPM['max'] ) {
			return new WP_Error(
				'dwp_rx_wpm',
				/* translators: 1: lowest allowed value, 2: highest allowed value. */
				sprintf( __( 'Words per minute must be a number from %1$d to %2$d.', 'core-functionality' ), self::WPM['min'], self::WPM['max'] )
			);
		}

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

		// Nothing checked sends no post types at all.
		$input['anchors_post_types'] ??= array();

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
