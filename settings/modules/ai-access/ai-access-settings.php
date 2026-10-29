<?php
/**
 * Defaults of the AI Access settings tab (module AiAccess, feature ContentEditing).
 *
 * The tab saves its choices as a whole; until then, these apply. A content type without a choice
 * here follows its profile: switched on, and a Rewrite & Republish copy only where the profile
 * says that is safe (posts and pages).
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'types' => array(),
);
