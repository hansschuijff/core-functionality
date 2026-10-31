<?php
/**
 * Automated Database Transient Optimizer Feature.
 *
 * Sweeps expired and orphaned WordPress transients clusters using atomic SQL bulk actions.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Cleaner\Features\TransientOptimizer
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Cleaner\Features\TransientOptimizer;

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;

// Import global PHP and WordPress core abstraction functions.
use function __;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_clear_scheduled_hook;
use function time;
use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TransientOptimizer
 *
 * Orchestrates the automated cron scheduling lifecycle and heavy bulk DB pruning routines.
 *
 * @since 1.0.0
 */
class TransientOptimizer extends AbstractFeature {

	/**
	 * The unique identification hook string handle mapped for cron event triggers.
	 *
	 * @var string
	 */
	private string $cron_hook_identifier = 'dwp_cf_cleaner_transient_cleanup_cron';

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'cleaner_transient_optimizer',
			name:          __( 'Transient Optimizer Feature', 'core-functionality' ),
			description:   __( 'Monitors, indexes, and flushes expired or orphaned core transients clusters using automated background crons to optimize the options table.', 'core-functionality' ),
		);
	}

	/**
	 * Registers the settings tab and the weekly cleanup cron event.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		// Register the settings page section.
		$this->register_settings_section();

		// Register the background asynchronous cleanup action worker.
		add_action( $this->cron_hook_identifier, array( $this, 'execute_bulk_transient_purge' ) );

		// Establish safety schedule gates: Hook a weekly optimization sweep event if currently vacant.
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time(), 'weekly', $this->cron_hook_identifier );
		}
	}

	/**
	 * Register the settings section of this feature.
	 *
	 * @return void
	 */
	private function register_settings_section() {
		// Securely register the configuration panel into the host page factory autonomously.
		add_action(
			'core-functionality/register-settings-sections',
			function ( $settings_factory ) {
				$settings_factory->register_section( TransientOptimizerSettings::class );
			}
		);
	}

	/**
	 * Performs a hard database sweep query executing garbage collection over expired options.
	 *
	 * Purges database rows and forcefully invalidates object caches to maintain real-time sync.
	 *
	 * @since  1.0.0
	 * @return int Total combined records successfully scrubbed from the options table.
	 */
	public function execute_bulk_transient_purge(): int {
		global $wpdb;

		// Transient timeouts are Unix (UTC) timestamps, so compare with time(), not current_time().
		$now_timestamp = time();

		// In LIKE, "_" matches any character; esc_like() makes it literal.
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' ) . '%';

		// 1. SELECT EXPIRED NAMES: Fetch the exact option names whose timeout markers have genuinely lapsed.
		$expired_timeouts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				   AND CAST(option_value AS UNSIGNED) < %d",
				$timeout_like,
				$now_timestamp
			)
		);

		if ( empty( $expired_timeouts ) || ! is_array( $expired_timeouts ) ) {
			$expired_timeouts = array();
		}

		$deleted_rows = 0;

		// 2. SQL TRANSACTION 1: Purge timeout and data rows in bulk
		if ( ! empty( $expired_timeouts ) ) {
			$option_names_to_delete = array();
			foreach ( $expired_timeouts as $timeout_name ) {
				$option_names_to_delete[] = $timeout_name;
				$option_names_to_delete[] = str_replace( '_transient_timeout_', '_transient_', $timeout_name );

				// CLEAN IN-MEMORY RUNTIME CACHE: Forcefully clean individual options cache paths.
				$transient_pure_key = str_replace( '_transient_timeout_', '', $timeout_name );
				wp_cache_delete( $transient_pure_key, 'transient' );
			}

			$placeholders = implode( ',', array_fill( 0, count( $option_names_to_delete ), '%s' ) );

			$result = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders holds only %s placeholders, one per name.
					"DELETE FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
					$option_names_to_delete
				)
			);
			if ( $result ) {
				$deleted_rows += (int) $result;
			}
		}

		// 3. SQL TRANSACTION 2: Scrub standalone orphaned timeouts
		$orphan_result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				   AND REPLACE(option_name, %s, %s) NOT IN (
					   SELECT option_name FROM (SELECT option_name FROM {$wpdb->options}) AS sub
				   )",
				$timeout_like,
				'_transient_timeout_',
				'_transient_'
			)
		);
		if ( $orphan_result ) {
			$deleted_rows += (int) $orphan_result;
		}

		// 4. GLOBAL OBJECT CACHE FLUSH: Force Redis/Memcached/APCu to instantly drop stored transients
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		return $deleted_rows;
	}

	/**
	 * De-registers scheduled events upon safe component dismantling lifecycles.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( $this->cron_hook_identifier );
	}
}
