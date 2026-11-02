<?php
/**
 * Tests for the ETag helpers RSL_Server uses when serving license XML
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Unit;

use RSL\Tests\TestCase;
use RSL_Server;

/**
 * Test RSL_Server ETag helpers
 *
 * @group unit
 * @group server
 */
class TestRSLServerEtag extends TestCase {

	/**
	 * Test that the ETag is the quoted SHA-256 digest of the body
	 *
	 * @covers RSL_Server::get_etag
	 */
	public function test_get_etag_is_quoted_sha256_of_body() {
		$xml = '<rsl xmlns="https://rslstandard.org/rsl"></rsl>';

		$this->assertSame( '"' . hash( 'sha256', $xml ) . '"', RSL_Server::get_etag( $xml ) );
	}

	/**
	 * Test that any change to the body changes the ETag
	 *
	 * @covers RSL_Server::get_etag
	 */
	public function test_get_etag_changes_with_body() {
		$this->assertNotSame(
			RSL_Server::get_etag( '<payment type="free"/>' ),
			RSL_Server::get_etag( '<payment type="purchase"/>' )
		);
	}

	/**
	 * Test If-None-Match matching
	 *
	 * @covers RSL_Server::etag_matches
	 */
	public function test_etag_matches() {
		$etag  = RSL_Server::get_etag( 'license' );
		$other = RSL_Server::get_etag( 'other license' );

		$this->assertTrue( RSL_Server::etag_matches( $etag, $etag ) );
		$this->assertTrue( RSL_Server::etag_matches( 'W/' . $etag, $etag ), 'Weak comparison ignores the W/ prefix' );
		$this->assertTrue( RSL_Server::etag_matches( $other . ', ' . $etag, $etag ), 'Any tag in the list can match' );
		$this->assertTrue( RSL_Server::etag_matches( '*', $etag ) );

		$this->assertFalse( RSL_Server::etag_matches( '', $etag ) );
		$this->assertFalse( RSL_Server::etag_matches( $other, $etag ) );
		$this->assertFalse( RSL_Server::etag_matches( trim( $etag, '"' ), $etag ), 'An unquoted tag does not match' );
	}
}
