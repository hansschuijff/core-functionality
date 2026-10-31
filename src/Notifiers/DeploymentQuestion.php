<?php
/**
 * Deployment Question.
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

use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Plugin;

use function __;
use function add_action;
use function add_query_arg;
use function array_key_exists;
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
 * Class DeploymentQuestion
 *
 * Asks an administrator, once, whether a new release may change where a module or feature runs
 * ("Draait op") when this site has its own value for it. Until the question is answered the site's
 * value holds, so an automatic update never overrules a local choice; the default answer is to take
 * the release value (Hans, October 2026). Both answers are final and the notice has no close button,
 * like ActivationQuestion. The value can still be changed on the Deployment tab.
 *
 * @since 1.0.0
 */
class DeploymentQuestion {

	/**
	 * Query arg holding the answer; also the base of the nonce action.
	 *
	 * @var string
	 */
	private const ANSWER_ARG = 'dwp_cf_deployment';

	/**
	 * Query arg holding the class name of the component that is answered for.
	 *
	 * @var string
	 */
	private const COMPONENT_ARG = 'dwp_cf_component';

	/**
	 * Answer that takes the release value.
	 *
	 * @var string
	 */
	private const ANSWER_ADOPT = 'adopt';

	/**
	 * Answer that keeps this site's value.
	 *
	 * @var string
	 */
	private const ANSWER_KEEP = 'keep';

	/**
	 * DeploymentQuestion Constructor.
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

		if ( ! $this->user_may_answer() || ! wp_verify_nonce( $nonce, $this->get_nonce_action( $fqcn ) ) ) {
			return;
		}

		$register  = $this->plugin->components_register;
		$questions = $register->get_deployment_questions();
		if ( in_array( $answer, array( self::ANSWER_ADOPT, self::ANSWER_KEEP ), true )
			&& array_key_exists( $fqcn, $questions )
		) {
			$adopt = self::ANSWER_ADOPT === $answer;
			$register->answer_deployment_question( $fqcn, $adopt );
			$this->plugin->notices->add( 'success', $this->get_answer_message( $fqcn, $adopt ) );
		}

		wp_safe_redirect( remove_query_arg( array( self::ANSWER_ARG, self::COMPONENT_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Prints the question for every module and feature whose release value differs from this site's value.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function print_question(): void {
		if ( ! $this->user_may_answer() ) {
			return;
		}

		$questions = $this->plugin->components_register->get_deployment_questions();
		if ( empty( $questions ) ) {
			return;
		}

		echo $this->render_question( $questions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while it is built.
	}

	/**
	 * Checks whether the current user may answer: an administrator or someone who may update plugins.
	 *
	 * @since  1.0.0
	 * @return bool True when the user may answer.
	 */
	private function user_may_answer(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'update_plugins' );
	}

	/**
	 * Returns the notice with the question.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $questions FQCN => the tier the release sets.
	 * @return string The notice (HTML).
	 */
	private function render_question( array $questions ): string {
		$intro_text = esc_html__( 'The new release changes where these modules and features may run, but this site has its own setting for them. Until you answer, this site\'s setting stays. Either answer is final; you can change it later under Settings → Core Functionality, Deployment Management.', 'core-functionality' );

		$items = array();
		foreach ( $questions as $fqcn => $release_value ) {
			$items[] = $this->render_component_item( $fqcn, $release_value );
		}
		$items_html = implode( '', $items );

		return <<<HTML
			<div class="notice notice-warning dwp-cf-deployment-question">
				<p><strong>Core Functionality</strong>: {$intro_text}</p>
				<ul>{$items_html}</ul>
			</div><!-- closes dwp-cf-deployment-question -->
		HTML;
	}

	/**
	 * Returns the list item of one component: its name, both values and both answers.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn          Class name of the module or feature.
	 * @param  string $release_value The tier the new release sets.
	 * @return string The list item (HTML).
	 */
	private function render_component_item( string $fqcn, string $release_value ): string {
		$name_html    = esc_html( $this->get_component_label( $fqcn ) );
		$site_label   = $this->get_tier_label( $this->plugin->components_register->get_deployment( $fqcn ) );
		$release_text = $this->get_tier_label( $release_value );
		$values_html  = esc_html(
			sprintf(
				/* translators: 1: this site's tier, 2: the tier of the new release. */
				__( 'runs on this site up to %1$s; the new release sets %2$s.', 'core-functionality' ),
				$site_label,
				$release_text
			)
		);
		$adopt_button = $this->render_answer_button( $fqcn, self::ANSWER_ADOPT, __( 'Follow the release', 'core-functionality' ), 'button-primary' );
		$keep_button  = $this->render_answer_button( $fqcn, self::ANSWER_KEEP, __( 'Keep this site\'s setting', 'core-functionality' ), 'button-secondary' );

		return <<<HTML
			<li style="margin-bottom: 8px;">
				<strong>{$name_html}</strong>: {$values_html}<br />
				{$adopt_button} {$keep_button}
			</li>
		HTML;
	}

	/**
	 * Returns the label of a tier value, or the value itself when it is unknown.
	 *
	 * @since  1.0.0
	 * @param  string $tier_value development, staging or production.
	 * @return string The label (not escaped).
	 */
	private function get_tier_label( string $tier_value ): string {
		$tier = Deployment::tryFrom( $tier_value );

		return null !== $tier ? $tier->get_label() : $tier_value;
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
	 * @param  string $answer       self::ANSWER_ADOPT or self::ANSWER_KEEP.
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
	 * @param  string $fqcn  Class name of the module or feature.
	 * @param  bool   $adopt Whether the release value was taken.
	 * @return string The message (not escaped; the Notices service filters it).
	 */
	private function get_answer_message( string $fqcn, bool $adopt ): string {
		$label = $this->get_component_label( $fqcn );

		return $adopt
			/* translators: %s: module or feature name. */
			? sprintf( __( '%s now follows the release.', 'core-functionality' ), $label )
			/* translators: %s: module or feature name. */
			: sprintf( __( '%s keeps this site\'s setting.', 'core-functionality' ), $label );
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
