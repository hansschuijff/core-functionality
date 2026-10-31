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
use function add_action;
use function add_query_arg;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function esc_url;
use function implode;
use function in_array;
use function remove_query_arg;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function wp_doing_ajax;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Class ActivationQuestion
 *
 * Asks an administrator, once, whether a module or feature that needs confirmation
 * (ComponentMeta::$confirm_activation) may run on this site. Until the question is answered the
 * component does not launch. Both answers are final, so the notice has no close button and never
 * returns once answered: it asks for a decision, it does not nag until something is switched on
 * (Hans, October 2026). The choice can still be changed on the Feature Activation tab.
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
	 * Query arg holding the class name of the component that is answered for.
	 *
	 * @var string
	 */
	private const COMPONENT_ARG = 'dwp_cf_component';

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
	}

	/**
	 * Handles a clicked answer: records it and goes back to the page without the answer in its URL.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_answer(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is verified below, per component.
		if ( wp_doing_ajax() || ! isset( $_GET[ self::ANSWER_ARG ], $_GET[ self::COMPONENT_ARG ], $_GET['_wpnonce'] ) ) {
			return;
		}

		$answer = sanitize_key( wp_unslash( $_GET[ self::ANSWER_ARG ] ) );
		$fqcn   = sanitize_text_field( wp_unslash( $_GET[ self::COMPONENT_ARG ] ) );
		$nonce  = sanitize_key( wp_unslash( $_GET['_wpnonce'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, $this->get_nonce_action( $fqcn ) ) ) {
			return;
		}

		$register = $this->plugin->components_register;
		if ( in_array( $answer, array( self::ANSWER_ENABLE, self::ANSWER_LEAVE_OFF ), true )
			&& in_array( $fqcn, $register->get_components_awaiting_confirmation(), true )
		) {
			$enable = self::ANSWER_ENABLE === $answer;
			$register->decide_activation( $fqcn, $enable );
			$this->plugin->notices->add( 'success', $this->get_answer_message( $fqcn, $enable ) );
		}

		wp_safe_redirect( remove_query_arg( array( self::ANSWER_ARG, self::COMPONENT_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Prints the question for every module and feature that waits for confirmation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function print_question(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$awaiting = $this->plugin->components_register->get_components_awaiting_confirmation();
		if ( empty( $awaiting ) ) {
			return;
		}

		echo $this->render_question( $awaiting ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while it is built.
	}

	/**
	 * Returns the notice with the question.
	 *
	 * @since  1.0.0
	 * @param  string[] $awaiting Class names of the components that wait for confirmation.
	 * @return string The notice (HTML).
	 */
	private function render_question( array $awaiting ): string {
		$intro_text = esc_html__( 'These modules and features run only after you confirm them. Either answer is final; you can change it later under Settings → Core Functionality, Feature Activation.', 'core-functionality' );

		$items = array();
		foreach ( $awaiting as $fqcn ) {
			$items[] = $this->render_component_item( $fqcn );
		}
		$items_html = implode( '', $items );

		return <<<HTML
			<div class="notice notice-warning dwp-cf-activation-question">
				<p><strong>Core Functionality</strong>: {$intro_text}</p>
				<ul>{$items_html}</ul>
			</div><!-- closes dwp-cf-activation-question -->
		HTML;
	}

	/**
	 * Returns the list item of one component: its name, module and description, and both answers.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Class name of the module or feature.
	 * @return string The list item (HTML).
	 */
	private function render_component_item( string $fqcn ): string {
		$name_html        = esc_html( $this->get_component_label( $fqcn ) );
		$description_html = esc_html( $fqcn::get_description() );
		$description_part = '' !== $description_html ? ' — ' . $description_html : '';
		$enable_button    = $this->render_answer_button( $fqcn, self::ANSWER_ENABLE, __( 'Switch on', 'core-functionality' ), 'button-primary' );
		$leave_off_button = $this->render_answer_button( $fqcn, self::ANSWER_LEAVE_OFF, __( 'Leave off', 'core-functionality' ), 'button-secondary' );

		return <<<HTML
			<li style="margin-bottom: 8px;">
				<strong>{$name_html}</strong>{$description_part}<br />
				{$enable_button} {$leave_off_button}
			</li>
		HTML;
	}

	/**
	 * Returns the name of a component, with its module for a feature.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Class name of the module or feature.
	 * @return string The label (not escaped).
	 */
	private function get_component_label( string $fqcn ): string {
		$module_fqcn = $this->plugin->components_register->get_module_of_feature( $fqcn );
		if ( false === $module_fqcn ) {
			return $fqcn::get_name();
		}

		/* translators: 1: feature name, 2: module name. */
		return sprintf( __( '%1$s (module %2$s)', 'core-functionality' ), $fqcn::get_name(), $module_fqcn::get_name() );
	}

	/**
	 * Returns one answer as a link styled as a button.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn         Class name of the module or feature.
	 * @param  string $answer       self::ANSWER_ENABLE or self::ANSWER_LEAVE_OFF.
	 * @param  string $label        The button text (not escaped).
	 * @param  string $button_class The WordPress button class.
	 * @return string The button (HTML).
	 */
	private function render_answer_button( string $fqcn, string $answer, string $label, string $button_class ): string {
		$answer_url  = add_query_arg(
			array(
				self::ANSWER_ARG    => $answer,
				self::COMPONENT_ARG => $fqcn,
			)
		);
		$answer_url  = esc_url( wp_nonce_url( $answer_url, $this->get_nonce_action( $fqcn ) ) );
		$label_html  = esc_html( $label );
		$class_value = 'button button-small ' . $button_class;

		return "<a class=\"{$class_value}\" href=\"{$answer_url}\">{$label_html}</a>";
	}

	/**
	 * Returns the message shown after an answer.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn   Class name of the module or feature.
	 * @param  bool   $enable Whether it was switched on.
	 * @return string The message (not escaped; the Notices service filters it).
	 */
	private function get_answer_message( string $fqcn, bool $enable ): string {
		$label = $this->get_component_label( $fqcn );

		return $enable
			/* translators: %s: module or feature name. */
			? sprintf( __( '%s is switched on.', 'core-functionality' ), $label )
			/* translators: %s: module or feature name. */
			: sprintf( __( '%s stays off.', 'core-functionality' ), $label );
	}

	/**
	 * Returns the nonce action for answering about one component.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Class name of the module or feature.
	 * @return string The nonce action.
	 */
	private function get_nonce_action( string $fqcn ): string {
		return self::ANSWER_ARG . '|' . $fqcn;
	}
}
