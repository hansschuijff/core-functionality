<?php
/**
 * Telegram Message Driver Implementation.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Notifiers\Drivers
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Notifiers\Drivers;

use DeWittePrins\CoreFunctionality\Notifiers\Interfaces\PushNoticeInterface;
use Override;

use function wp_remote_post;

/**
 * Class Telegram
 *
 * Handles the configuration profile and transmission trigger for Telegram bot alerts.
 *
 * @since 1.0.0
 */
class Telegram implements PushNoticeInterface {

	/**
	 * Constructor encapsulating platform-specific routing variables.
	 *
	 * @since 1.0.0
	 * @param array{bot_token?: string, chat_id?: string} $settings Authorization and destination configurations.
	 */
	public function __construct( private readonly array $settings ) {}

	/**
	 * Executes the platform-specific Telegram API request.
	 *
	 * @since 1.0.0
	 * @param string $message The alert text payload to push.
	 * @param string $title   Optional title (Telegram has none; ignored).
	 * @return bool True if successfully initiated, false otherwise.
	 */
	#[Override]
	public function send( string $message, string $title = '' ): bool {
		$bot_token = $this->settings['bot_token'] ?? '';
		$chat_id   = $this->settings['chat_id'] ?? '';

		// Guard: If settings are incomplete, silently abort without crashing the core system loop.
		if ( empty( $bot_token ) || empty( $chat_id ) ) {
			return false;
		}

		$url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

		wp_remote_post(
			$url,
			array(
				'body' => array(
					'chat_id'    => $chat_id,
					'text'       => '🚨 *[DeWittePrins Alert]*' . "\n\n" . $message,
					'parse_mode' => 'Markdown',
				),
			)
		);

		return true;
	}

	/**
	 * Returns the name of this push notice driver.
	 *
	 * @since  1.0.0
	 * @return string The driver name.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Telegram (free bot)', 'core-functionality' );
	}

	/**
	 * Returns the settings fields this driver needs.
	 *
	 * @since  1.0.0
	 * @return array The configuration fields.
	 */
	#[Override]
	public static function get_configuration_schema(): array {
		return array(
			'bot_token' => array(
				'type'  => 'text',
				'label' => __( 'Telegram Bot Token', 'core-functionality' ),
				'desc'  => __( 'Obtained via Telegram BotFather (e.g., 123456:ABC-def).', 'core-functionality' ),
			),
			'chat_id'   => array(
				'type'  => 'text',
				'label' => __( 'Telegram Chat ID', 'core-functionality' ),
				'desc'  => __( 'The target private user or group chat ID window.', 'core-functionality' ),
			),
		);
	}
}
