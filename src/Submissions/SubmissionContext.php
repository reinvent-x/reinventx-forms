<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

/**
 * Where a submission came from. Everything here is untrusted visitor input
 * or request metadata; it is stored for display (always escaped on output)
 * and never used to make decisions.
 */
final class SubmissionContext {

	public const MAX_URL   = 2000;
	public const MAX_TITLE = 300;
	public const MAX_UA    = 255;

	private function __construct(
		public readonly ?string $sourceUrl,
		public readonly ?string $sourceTitle,
		public readonly ?string $referrerUrl,
		public readonly ?string $userAgent,
		public readonly ?string $ipHash,
		public readonly ?string $rateLimitKey,
	) {
	}

	/**
	 * Build from raw values, truncating and blanking as needed. The IP is
	 * HMAC-hashed with the given secret — the raw address is never stored.
	 *
	 * Two independent hashes come out of the address, under different
	 * contexts so neither can be correlated with the other:
	 *
	 * - $ipHash is persisted on the lead, and only when $store_ip_hash.
	 * - $rateLimitKey is never persisted; it lives only in a short-lived
	 *   transient key. It is derived regardless of $store_ip_hash so that
	 *   turning off IP storage stays a privacy choice and does not quietly
	 *   switch off abuse protection.
	 */
	public static function fromRaw(
		?string $source_url,
		?string $source_title,
		?string $referrer_url,
		?string $user_agent,
		?string $ip,
		string $ip_secret,
		bool $store_ip_hash = true
	): self {
		$has_ip = null !== $ip && '' !== $ip;

		return new self(
			self::clean( $source_url, self::MAX_URL ),
			self::clean( $source_title, self::MAX_TITLE ),
			self::clean( $referrer_url, self::MAX_URL ),
			self::clean( $user_agent, self::MAX_UA ),
			$has_ip && $store_ip_hash ? hash_hmac( 'sha256', 'store|' . $ip, $ip_secret ) : null,
			$has_ip ? hash_hmac( 'sha256', 'rate|' . $ip, $ip_secret ) : null,
		);
	}

	private static function clean( ?string $value, int $max ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) );

		if ( '' === $value ) {
			return null;
		}

		return mb_substr( $value, 0, $max );
	}
}
