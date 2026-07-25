<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Submissions;

use PHPUnit\Framework\TestCase;
use Reinventx\Submissions\SubmissionContext;

final class SubmissionContextTest extends TestCase {

	private function context( ?string $ip, bool $store_ip_hash = true ): SubmissionContext {
		return SubmissionContext::fromRaw(
			'https://example.com/contact',
			'Contact us',
			'https://google.com/search',
			'Mozilla/5.0 Test',
			$ip,
			'test-secret',
			$store_ip_hash
		);
	}

	public function testStoresHashAndRateKeyWhenIpStorageEnabled(): void {
		$context = $this->context( '203.0.113.7' );

		$this->assertNotNull( $context->ipHash );
		$this->assertNotNull( $context->rateLimitKey );
	}

	/**
	 * The regression this guards: opting out of IP storage used to null the
	 * address outright, which silently disabled rate limiting too.
	 */
	public function testRateKeySurvivesWhenIpStorageDisabled(): void {
		$context = $this->context( '203.0.113.7', false );

		$this->assertNull( $context->ipHash, 'nothing IP-derived may be persisted' );
		$this->assertNotNull( $context->rateLimitKey, 'rate limiting must still have a key' );
	}

	public function testRateKeyIsStableForTheSameAddress(): void {
		$this->assertSame(
			$this->context( '203.0.113.7', false )->rateLimitKey,
			$this->context( '203.0.113.7' )->rateLimitKey
		);
	}

	public function testRateKeyDiffersPerAddress(): void {
		$this->assertNotSame(
			$this->context( '203.0.113.7' )->rateLimitKey,
			$this->context( '203.0.113.8' )->rateLimitKey
		);
	}

	/**
	 * The two hashes are derived under different contexts, so the value held
	 * in a transient cannot be correlated with the value stored on the lead.
	 */
	public function testStoredHashAndRateKeyAreNotTheSameValue(): void {
		$context = $this->context( '203.0.113.7' );

		$this->assertNotSame( $context->ipHash, $context->rateLimitKey );
	}

	public function testNoKeysWithoutAnAddress(): void {
		$context = $this->context( null );

		$this->assertNull( $context->ipHash );
		$this->assertNull( $context->rateLimitKey );
	}
}
