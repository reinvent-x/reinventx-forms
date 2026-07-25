<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

/**
 * Per-client submission throttle backed by transients.
 *
 * Best-effort by design: transients are not atomic, so a burst can slightly
 * overshoot the limit. That is acceptable — this exists to blunt dumb abuse,
 * not to be a security boundary.
 */
final class RateLimiter {

	public const MAX_PER_WINDOW  = 5;
	public const WINDOW_SECONDS  = 60;
	private const TRANSIENT_BASE = 'rvtx_rl_';

	/**
	 * Record one attempt for the client key and report whether it is allowed.
	 *
	 * @param string $key Client identifier (the IP hash).
	 */
	public function allow( string $key ): bool {
		/**
		 * Filters how many submissions one client may make per minute.
		 *
		 * @param int $max Maximum submissions per window (default 5).
		 */
		$max = (int) apply_filters( 'rvtx_rate_limit_max', self::MAX_PER_WINDOW );

		$transient = self::TRANSIENT_BASE . substr( $key, 0, 32 );
		$count     = (int) get_transient( $transient );

		if ( $count >= $max ) {
			return false;
		}

		set_transient( $transient, $count + 1, self::WINDOW_SECONDS );

		return true;
	}
}
