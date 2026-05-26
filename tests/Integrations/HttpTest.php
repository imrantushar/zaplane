<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Http;
use Zaplane\Tests\WPMocks;

/**
 * Contract-only stub for the HTTP integration. The high-risk bits
 * (SSRF allowlist, response parsing) deserve hand-written tests in a
 * follow-up — this file just locks down the contract surface.
 */
class HttpTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Http::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		// Pre-arm wp_remote_request so execute_node doesn't fall through
		// to the WP_Error branch. setHttpResponse takes an array; it
		// json-encodes internally so wp_remote_retrieve_body returns a
		// JSON string that http.php json_decode()s back.
		WPMocks::setHttpResponse( [ 'ok' => true ], 200 );
	}

	protected function getActionTests(): array {
		return [
			'request' => [
				'url'    => 'https://example.com/health',
				'method' => 'GET',
			],
		];
	}
}
