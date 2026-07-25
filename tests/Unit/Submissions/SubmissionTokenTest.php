<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Submissions;

use Reinventx\Submissions\SubmissionToken;
use PHPUnit\Framework\TestCase;

final class SubmissionTokenTest extends TestCase {

	private SubmissionToken $token;

	protected function setUp(): void {
		$this->token = new SubmissionToken( 'test-secret' );
	}

	public function testValidTokenWithinWindowVerifies(): void {
		$issued = 1000;
		$token  = $this->token->issue( 7, $issued );

		$this->assertTrue(
			$this->token->verify( 7, $issued, $token, $issued + SubmissionToken::MIN_AGE_SECONDS )
		);
	}

	public function testTooFastSubmissionIsRejected(): void {
		$issued = 1000;
		$token  = $this->token->issue( 7, $issued );

		$this->assertFalse(
			$this->token->verify( 7, $issued, $token, $issued + SubmissionToken::MIN_AGE_SECONDS - 1 )
		);
	}

	public function testExpiredTokenIsRejected(): void {
		$issued = 1000;
		$token  = $this->token->issue( 7, $issued );

		$this->assertFalse(
			$this->token->verify( 7, $issued, $token, $issued + SubmissionToken::MAX_AGE_SECONDS + 1 )
		);
	}

	public function testTokenIsBoundToTheForm(): void {
		$issued = 1000;
		$token  = $this->token->issue( 7, $issued );

		$this->assertFalse( $this->token->verify( 8, $issued, $token, $issued + 10 ) );
	}

	public function testTamperedTimestampIsRejected(): void {
		$issued = 1000;
		$token  = $this->token->issue( 7, $issued );

		// Claiming a different issue time invalidates the signature.
		$this->assertFalse( $this->token->verify( 7, $issued + 100, $token, $issued + 110 ) );
	}

	public function testDifferentSecretsProduceDifferentTokens(): void {
		$other = new SubmissionToken( 'other-secret' );

		$this->assertNotSame(
			$this->token->issue( 7, 1000 ),
			$other->issue( 7, 1000 )
		);
	}
}
