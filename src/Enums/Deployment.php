<?php
/**
 * File containing the Deployment Enum.
 *
 * @package DeWittePrins\CoreFunctionality\Enums
 * @author  Hans Schuijff @hansschuijff
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum representing the current release and environment maturity of a Module or Feature.
 *
 * Used by the Kernel to filter component activation based on the server environment.
 *
 * @since 1.0.0
 */
enum Deployment: string {

	case DEVELOPMENT = 'development';
	case STAGING     = 'staging';
	case PRODUCTION  = 'production';

	/**
	 * Returns the localized human-readable label for the status.
	 *
	 * @since  1.0.0
	 * @return string The translated status label.
	 */
	public function get_label(): string {
		return match ( $this ) {
			self::DEVELOPMENT => __( '🧪 Development', 'core-functionality' ),
			self::STAGING     => __( '🔬 Staging', 'core-functionality' ),
			self::PRODUCTION  => __( '🌐 Production', 'core-functionality' ),
		};
	}

	/**
	 * Returns the position of the tier, from development (0) to production (2).
	 *
	 * @since  1.0.0
	 * @return int The rank of the tier.
	 */
	public function get_rank(): int {
		return match ( $this ) {
			self::DEVELOPMENT => 0,
			self::STAGING     => 1,
			self::PRODUCTION  => 2,
		};
	}

	/**
	 * Checks whether a component of this tier may run on a server of the given tier.
	 *
	 * A development server runs everything, staging runs staging and production components,
	 * production only production components.
	 *
	 * @since  1.0.0
	 * @param  Deployment $server_tier The tier of the current server.
	 * @return bool True when the component may run there.
	 */
	public function allows( Deployment $server_tier ): bool {
		return $this->get_rank() >= $server_tier->get_rank();
	}

	/**
	 * Returns the more restrictive of this tier and another one.
	 *
	 * @since  1.0.0
	 * @param  Deployment $other The other tier.
	 * @return Deployment The tier with the lower rank.
	 */
	public function lowest_with( Deployment $other ): Deployment {
		return $other->get_rank() < $this->get_rank() ? $other : $this;
	}
}
