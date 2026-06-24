<?php

namespace Zaplane\Tests\Integrations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Drift detection across the entire integration registry.
 *
 * One test class. N integrations. Catches the kinds of bugs that only
 * appear when something is added, removed, or renamed and the registry,
 * the source file, and the test file fall out of sync.
 *
 * What is asserted (each method runs once for the whole repo):
 *   - the registry has no duplicate class entries
 *   - every registry slug points to a file present in /integrations/
 *     with the exact case spelled out (catches the Slack.php /
 *     slack.php Linux-deploy fatal class)
 *   - every registry class autoloads
 *   - every registry class extends IntegrationBase
 *   - every integration's get_slug() matches its registry key
 *   - every integration has a *Test.php in tests/Integrations/
 *     (or is parked under _pending/ while it is being written)
 *   - every webhook-supporting integration overrides
 *     verify_webhook_signature() — otherwise its incoming-webhook
 *     route is unauthenticated
 *   - every connection-requiring integration overrides
 *     test_connection() — otherwise the create-connection UI cannot
 *     validate credentials
 */
class ParityTest extends TestCase {

	/**
	 * @return array<string, array{file:string,class:string}>
	 */
	private static function registry(): array {
		$bag = require __DIR__ . '/../../includes/config/integrations.php';
		return $bag['registry'] ?? [];
	}

	private static function integrations_dir(): string {
		return realpath( __DIR__ . '/../../integrations' );
	}

	public function test_registry_has_no_duplicate_classes(): void {
		$seen = [];
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			if ( isset( $seen[ $cls ] ) ) {
				$this->fail( "Class {$cls} is registered under two slugs: `{$seen[$cls]}` and `{$slug}`." );
			}
			$seen[ $cls ] = $slug;
		}
		$this->assertNotEmpty( $seen );
	}

	public function test_every_registry_file_exists_on_disk_case_sensitive(): void {
		$dir    = self::integrations_dir();
		$listed = scandir( $dir );
		foreach ( self::registry() as $slug => $meta ) {
			$file = $meta['file'];
			$this->assertContains(
				$file,
				$listed,
				"Registry slug `{$slug}` declares file `{$file}` but no file with that exact case "
				. 'exists in /integrations/. macOS APFS hides case mismatches; Linux production fatals.'
			);
		}
	}

	public function test_every_registry_class_autoloads(): void {
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			$this->assertTrue(
				class_exists( $cls ),
				"Registry slug `{$slug}` declares class `{$cls}` but the autoloader could not resolve it."
			);
		}
	}

	public function test_every_registry_class_extends_integration_base(): void {
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			if ( ! class_exists( $cls ) ) {
				continue;
			}
			$this->assertTrue(
				is_subclass_of( $cls, IntegrationBase::class ),
				"Class `{$cls}` (slug `{$slug}`) must extend " . IntegrationBase::class
			);
		}
	}

	public function test_every_integration_slug_matches_registry_key(): void {
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			if ( ! class_exists( $cls ) ) {
				continue;
			}
			$declared = $cls::get_slug();
			$this->assertSame(
				$slug,
				$declared,
				"Class {$cls} declares slug `{$declared}` but is registered as `{$slug}`."
			);
		}
	}

	public function test_every_integration_has_test_or_is_pending(): void {
		$test_dir    = __DIR__;
		$pending_dir = __DIR__ . '/_pending';

		foreach ( self::registry() as $slug => $meta ) {
			$cls   = $meta['class'];
			if ( ! class_exists( $cls ) ) {
				continue;
			}
			$short = ( new ReflectionClass( $cls ) )->getShortName();
			$file  = "{$short}Test.php";

			$has = file_exists( "{$test_dir}/{$file}" )
				|| file_exists( "{$pending_dir}/{$file}" );

			$this->assertTrue(
				$has,
				"Integration `{$slug}` ({$cls}) has no test at tests/Integrations/{$file}. "
				. 'Write it, or park a stub under _pending/.'
			);
		}
	}

	public function test_webhook_integrations_override_signature_verification(): void {
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			if ( ! class_exists( $cls ) || ! $cls::supports_webhook() ) {
				continue;
			}

			$ref = new ReflectionMethod( $cls, 'verify_webhook_signature' );
			$this->assertNotSame(
				IntegrationBase::class,
				$ref->getDeclaringClass()->getName(),
				"Integration `{$slug}` declares supports_webhook() = true but inherits the default "
				. 'verify_webhook_signature() which returns true unconditionally. Override it, or '
				. 'the incoming-webhook endpoint for this integration is publicly unauthenticated.'
			);
		}
	}

	public function test_connection_integrations_override_test_connection(): void {
		foreach ( self::registry() as $slug => $meta ) {
			$cls = $meta['class'];
			if ( ! class_exists( $cls ) || ! $cls::requires_connection() ) {
				continue;
			}

			$ref = new ReflectionMethod( $cls, 'test_connection' );
			$this->assertNotSame(
				IntegrationBase::class,
				$ref->getDeclaringClass()->getName(),
				"Integration `{$slug}` declares requires_connection() = true but inherits the default "
				. 'test_connection() which always returns ok. Override it so the create-connection '
				. 'UI can actually validate credentials.'
			);
		}
	}
}
