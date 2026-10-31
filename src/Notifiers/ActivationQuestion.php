<?php
/**
 * Activation Question.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Notifiers
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Notifiers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Plugin;

use function __;
use function _n;
use function add_action;
use function add_query_arg;
use function count;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function esc_url;
use function in_array;
use function plugins_url;
use function remove_query_arg;
use function sanitize_key;
use function wp_doing_ajax;
use function wp_enqueue_style;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Class ActivationQuestion
 *
 * Asks an administrator, once, whether the modules and features that need confirmation
 * (ComponentMeta::$confirm_activation) may be activated on this site. Until the question is
 * answered they do not launch. One answer covers the whole list: activate all or leave them
 * inactive; choosing per component is what the Feature Activation tab is for. The notice has no
 * close button and does not return once answered: it asks for a decision, it does not nag until
 * something is activated (Hans, October 2026).
 *
 * @since 1.0.0
 */
class ActivationQuestion {

	/**
	 * Query arg holding the answer; also the base of the nonce action.
	 *
	 * @var string
	 */
	private const ANSWER_ARG = 'dwp_cf_activation';

	/**
	 * Answer that switches the component on.
	 *
	 * @var string
	 */
	private const ANSWER_ENABLE = 'enable';

	/**
	 * Answer that leaves the component off.
	 *
	 * @var string
	 */
	private const ANSWER_LEAVE_OFF = 'leave-off';

	/**
	 * ActivationQuestion Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {
		add_action( 'admin_init', array( $this, 'handle_answer' ) );
		add_action( 'admin_notices', array( $this, 'print_question' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Handles a clicked answer: records it and goes back to the page without the answer in its URL.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_answer(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is verified below.
		if ( wp_doing_ajax() || ! isset( $_GET[ self::ANSWER_ARG ], $_GET['_wpnonce'] ) ) {
			return;
		}

		$answer = sanitize_key( wp_unslash( $_GET[ self::ANSWER_ARG ] ) );
		$nonce  = sanitize_key( wp_unslash( $_GET['_wpnonce'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::ANSWER_ARG ) ) {
			return;
		}

		if ( in_array( $answer, array( self::ANSWER_ENABLE, self::ANSWER_LEAVE_OFF ), true ) ) {
			$this->record_answer( self::ANSWER_ENABLE === $answer );
		}

		wp_safe_redirect( remove_query_arg( array( self::ANSWER_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Records one answer for every component that waits for confirmation.
	 *
	 * Only "yes" gets a message; after "no" the notice simply goes away. Where to activate
	 * them later is already said under "Read more" (Hans, October 2026).
	 *
	 * @since  1.0.0
	 * @param  bool $enable Whether the components are activated.
	 * @return void
	 */
	private function record_answer( bool $enable ): void {
		$register = $this->plugin->components_register;
		$awaiting = $register->get_components_awaiting_confirmation();

		foreach ( $awaiting as $fqcn ) {
			$register->decide_activation( $fqcn, $enable );
		}

		if ( $enable && ! empty( $awaiting ) ) {
			$this->plugin->notices->add( 'success', _n( 'The new feature is active.', 'The new features are active.', count( $awaiting ), 'core-functionality' ) );
		}
	}

	/**
	 * Loads the style of the question on the admin screens where it is shown.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_style(): void {
		if ( ! $this->should_ask() ) {
			return;
		}

		wp_enqueue_style(
			'dwp-cf-activation-question',
			plugins_url( 'assets/css/activation-question.css', __FILE__ ),
			array(),
			$this->plugin->get_data( 'version' )
		);
	}

	/**
	 * Whether the current user should see the question.
	 *
	 * @since  1.0.0
	 * @return bool True when something waits for confirmation and the user may answer.
	 */
	private function should_ask(): bool {
		return current_user_can( 'manage_options' )
			&& ! empty( $this->plugin->components_register->get_components_awaiting_confirmation() );
	}

	/**
	 * Prints the question for every module and feature that waits for confirmation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function print_question(): void {
		if ( ! $this->should_ask() ) {
			return;
		}

		$awaiting = $this->plugin->components_register->get_components_awaiting_confirmation();

		echo $this->render_question( $awaiting ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while it is built.
	}

	/**
	 * Returns the notice: the list of components, the question with both answers, and "Read more".
	 *
	 * @since  1.0.0
	 * @param  string[] $awaiting Class names of the components that wait for confirmation.
	 * @return string The notice (HTML).
	 */
	private function render_question( array $awaiting ): string {
		$count         = count( $awaiting );
		$title_text    = esc_html( _n( 'Core Functionality has a new feature that is not active yet:', 'Core Functionality has new features that are not active yet:', $count, 'core-functionality' ) );
		$question_text = esc_html( _n( 'May I activate it for you?', 'May I activate them for you?', $count, 'core-functionality' ) );
		$list_html     = $this->render_component_list( $awaiting );
		$yes_button    = $this->render_answer_button( self::ANSWER_ENABLE, __( 'Yes', 'core-functionality' ), 'button-primary' );
		$no_button     = $this->render_answer_button( self::ANSWER_LEAVE_OFF, __( 'No', 'core-functionality' ), 'button-secondary' );
		$more_html     = $this->render_read_more( $awaiting );

		return <<<HTML
			<div class="notice notice-info dwp-cf-activation-question">
				<p class="dwp-cf-question-title"><strong>{$title_text}</strong></p>
				{$list_html}
				<div class="dwp-cf-question-actions">
					<span>{$question_text}</span>
					{$yes_button}
					{$no_button}
				</div><!-- closes dwp-cf-question-actions -->
				{$more_html}
			</div><!-- closes dwp-cf-activation-question -->
		HTML;
	}

	/**
	 * Returns the list of component names, one per line.
	 *
	 * @since  1.0.0
	 * @param  string[] $awaiting Class names of the components that wait for confirmation.
	 * @return string The list (HTML).
	 */
	private function render_component_list( array $awaiting ): string {
		$items = '';
		foreach ( $awaiting as $fqcn ) {
			$name_html = esc_html( $this->plugin->components_register->get_display_name( $fqcn ) );
			$items    .= "<li>{$name_html}</li>";
		}

		return "<ul class=\"dwp-cf-question-list\">{$items}</ul>";
	}

	/**
	 * Returns the collapsed "Read more": what each component does and where to activate it later.
	 *
	 * @since  1.0.0
	 * @param  string[] $awaiting Class names of the components that wait for confirmation.
	 * @return string The details block (HTML).
	 */
	private function render_read_more( array $awaiting ): string {
		$summary_text = esc_html__( 'Read more', 'core-functionality' );
		$later_text   = esc_html__( 'If you do not want to use the new features yet, you can still activate them later in the Settings, under Core Functionality → Feature Activation.', 'core-functionality' );

		$descriptions = '';
		foreach ( $awaiting as $fqcn ) {
			$name_html        = esc_html( $this->plugin->components_register->get_display_name( $fqcn ) );
			$description_html = esc_html( $fqcn::get_description() );
			$descriptions    .= "<dt>{$name_html}</dt><dd>{$description_html}</dd>";
		}

		return <<<HTML
			<details class="dwp-cf-question-more">
				<summary>{$summary_text}</summary>
				<dl>{$descriptions}</dl>
				<p>{$later_text}</p>
			</details><!-- closes dwp-cf-question-more -->
		HTML;
	}

	/**
	 * Returns one answer as a link styled as a button.
	 *
	 * @since  1.0.0
	 * @param  string $answer       self::ANSWER_ENABLE or self::ANSWER_LEAVE_OFF.
	 * @param  string $label        The button text (not escaped).
	 * @param  string $button_class The WordPress button class.
	 * @return string The button (HTML).
	 */
	private function render_answer_button( string $answer, string $label, string $button_class ): string {
		$answer_url  = esc_url( wp_nonce_url( add_query_arg( self::ANSWER_ARG, $answer ), self::ANSWER_ARG ) );
		$label_html  = esc_html( $label );
		$class_value = 'button ' . $button_class;

		return "<a class=\"{$class_value}\" href=\"{$answer_url}\">{$label_html}</a>";
	}
}
