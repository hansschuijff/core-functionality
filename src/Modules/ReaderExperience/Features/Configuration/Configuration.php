<?php
/**
 * Reader Experience Configuration Feature.
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

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use Override;

use function __;
use function add_action;
use function add_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function is_array;

/**
 * Class Configuration
 *
 * Steers the Reader Experience plugin with the choices of its settings tab. That plugin has no
 * options of its own, only filters; this feature answers them. The kernel launches it on init 0,
 * before Reader Experience builds its modules (init 10) and long before it reads the filters.
 * Code on a later priority (a theme, a mu-plugin) can still override a choice, per post if need be.
 *
 * @since 1.0.0
 */
class Configuration extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'reader_experience_configuration',
			name:        __( 'Reader Experience Settings', 'core-functionality' ),
			description: __( 'A settings tab for the Reader Experience plugin, which itself is steered by filters only.', 'core-functionality' ),
			status:      Deployment::PRODUCTION,
		);
	}

	/**
	 * Registers the settings tab and answers the filters of Reader Experience.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( ConfigurationSettings::class );
			}
		);

		$this->add_filters( ConfigurationSettings::get_values( $this->plugin ) );
	}

	/**
	 * Hooks the saved choices into the filters of Reader Experience.
	 *
	 * @since  1.0.0
	 * @param  array $values The normalized settings (see ConfigurationSettings::get_values()).
	 * @return void
	 */
	private function add_filters( array $values ): void {
		// Heading anchors: switched off means no post types at all.
		add_filter(
			'reader_experience/heading_anchors_post_types',
			fn () => $values['anchors_enabled'] ? $values['anchors_post_types'] : array()
		);
		add_filter( 'reader_experience/heading_anchors_load_css', fn () => $values['anchors_load_css'] );
		add_filter( 'reader_experience/heading_anchors_load_js', fn () => $values['anchors_load_js'] );
		add_filter(
			'reader_experience/heading_anchors_config',
			fn ( $config ) => $this->filter_anchors_config( is_array( $config ) ? $config : array(), $values )
		);

		// Extra reserved ids come on top of the plugin's own list, which stays.
		add_filter(
			'reader_experience/reserved_ids',
			fn ( $ids ) => array_values( array_unique( array_merge( is_array( $ids ) ? $ids : array(), $values['anchors_reserved_ids'] ) ) )
		);

		add_filter( 'reader_experience/words_per_minute', fn () => $values['reading_wpm'] );
		add_filter( 'reader_experience/notify_on_feedback', fn () => $values['feedback_notify'] );
		add_filter( 'reader_experience/rating_spam_check', fn () => $values['feedback_spam_check'] );
	}

	/**
	 * Applies the chosen position, containers and labels to the anchor script's options. What is
	 * left empty keeps the plugin's own default (its translated labels, its container list).
	 *
	 * @since  1.0.0
	 * @param  array $config The options as the plugin offers them.
	 * @param  array $values The normalized settings.
	 * @return array The options.
	 */
	private function filter_anchors_config( array $config, array $values ): array {
		$config['position'] = $values['anchors_position'];

		if ( array() !== $values['anchors_containers'] ) {
			$config['container'] = $values['anchors_containers'];
		}

		$labels = is_array( $config['labels'] ?? null ) ? $config['labels'] : array();
		if ( '' !== $values['anchors_label_copy'] ) {
			$labels['copy'] = $values['anchors_label_copy'];
		}
		if ( '' !== $values['anchors_label_copied'] ) {
			$labels['copied'] = $values['anchors_label_copied'];
		}
		$config['labels'] = $labels;

		return $config;
	}
}
