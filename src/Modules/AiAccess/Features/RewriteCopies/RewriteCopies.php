<?php
/**
 * AI Rewrite Copies Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\RewriteCopies
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\RewriteCopies;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentServices;
use Override;
use WP_Post;
use Yoast\WP\Duplicate_Post\Permissions_Helper;

use function __;
use function add_filter;
use function get_post_meta;
use function is_array;

/**
 * Class RewriteCopies
 *
 * Lets an AI assistant change published content in a Rewrite & Republish copy of Yoast
 * Duplicate Post. The copy is a draft; the original is only replaced when the site owner
 * publishes the copy, and it keeps its own id (so tickets, orders and enrolments stay linked).
 *
 * @since 1.0.0
 */
class RewriteCopies extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'ai_access_rewrite_copies',
			name:          __( 'Rewrite Copies', 'core-functionality' ),
			description:   __( 'An AI assistant may change published content in a Rewrite & Republish copy (Yoast Duplicate Post) that replaces the original only when you publish it.', 'core-functionality' ),
			dependencies:  'duplicate-post',
			uses_features: array( ContentEditing::class ),
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_filter( 'core-functionality/ai-access-rewrite-available', '__return_true' );
		add_filter( 'core-functionality/ai-access-abilities', array( $this, 'add_ability' ), 10, 2 );
		add_filter( 'core-functionality/ai-access-content-summary', array( $this, 'add_copy_links' ), 10, 2 );
	}

	/**
	 * Adds the ability that starts a copy.
	 *
	 * @since  1.0.0
	 * @param  mixed           $abilities The abilities.
	 * @param  ContentServices $services  The shared content services.
	 * @return array The abilities.
	 */
	public function add_ability( mixed $abilities, ContentServices $services ): array {
		$abilities   = is_array( $abilities ) ? $abilities : array();
		$abilities[] = new StartRewrite( $services, new Permissions_Helper() );

		return $abilities;
	}

	/**
	 * Adds to a summary which copy belongs to an original, or which original a copy replaces.
	 *
	 * @since  1.0.0
	 * @param  mixed   $summary The summary.
	 * @param  WP_Post $post    The post.
	 * @return mixed The summary.
	 */
	public function add_copy_links( mixed $summary, WP_Post $post ): mixed {
		if ( ! is_array( $summary ) ) {
			return $summary;
		}

		$helper = new Permissions_Helper();
		if ( $helper->is_rewrite_and_republish_copy( $post ) ) {
			$summary['rewrite_of'] = (int) get_post_meta( $post->ID, '_dp_original', true );
		} elseif ( $helper->has_rewrite_and_republish_copy( $post ) ) {
			$summary['rewrite_copy_id'] = (int) $helper->get_rewrite_and_republish_copy_id( $post );
		}

		return $summary;
	}
}
