<?php
/**
 * Master Registry Index for Core Framework Modules.
 *
 * Maps abstract module identifiers to their respective physical layout tracks on disk.
 *
 * @package DeWittePrins\CoreFunctionality
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'admin_toolbar'     => 'admin-toolbar/',
	'active-woo'        => 'active-woo/',
	'reader-experience' => 'reader-experience/',
	'events'            => 'events/',
	'ai-access'         => 'ai-access/',
	'duplicate-post'    => 'duplicate-post/',
	'themes'            => 'themes/',
);
