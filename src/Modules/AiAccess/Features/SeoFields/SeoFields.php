<?php
/**
 * AI SEO Fields Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\SeoFields
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\SeoFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use Override;

use function __;
use function add_filter;
use function is_array;

/**
 * Class SeoFields
 *
 * Lets an AI assistant read and write the SEO title and meta description of Yoast SEO.
 *
 * @since 1.0.0
 */
class SeoFields extends AbstractFeature {

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:            'ai_access_seo_fields',
			name:          __( 'SEO Fields', 'core-functionality' ),
			description:   __( 'An AI assistant may write the SEO title and meta description of Yoast SEO.', 'core-functionality' ),
			dependencies:  'yoast-seo',
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
		add_filter( 'core-functionality/ai-access-field-handlers', array( $this, 'add_field_handler' ) );
	}

	/**
	 * Adds the SEO fields.
	 *
	 * @since  1.0.0
	 * @param  mixed $handlers The handlers.
	 * @return array The handlers.
	 */
	public function add_field_handler( mixed $handlers ): array {
		$handlers   = is_array( $handlers ) ? $handlers : array();
		$handlers[] = new YoastSeoFields();

		return $handlers;
	}
}
