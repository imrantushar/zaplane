<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\ImageHelper;

/**
 * Image Helper: reads an image's size; local sources must be in uploads.
 */
class ImageHelperTest extends IntegrationTestCase {

	/** A 1×1 PNG. */
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	protected function getIntegrationClass(): string {
		return ImageHelper::class;
	}

	private function info( string $source ): array {
		return ImageHelper::execute_node( $this->makeActionNode( 'info', [ 'source' => $source ] ), [] )['data'];
	}

	public function test_reads_an_image_in_uploads(): void {
		$file = wp_upload_dir()['basedir'] . '/zaplane-test-pixel.png';
		file_put_contents( $file, base64_decode( self::PNG ) );
		$data = $this->info( $file );
		unlink( $file );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 1, $data['width'] );
		$this->assertSame( 'image/png', $data['mime'] );
	}

	public function test_files_outside_uploads_are_refused(): void {
		$outside = tempnam( sys_get_temp_dir(), 'zimg' );
		file_put_contents( $outside, base64_decode( self::PNG ) );
		$data = $this->info( $outside );
		$dotdot = $this->info( wp_upload_dir()['basedir'] . '/../' . basename( $outside ) );
		unlink( $outside );
		$this->assertFalse( $data['success'] );
		$this->assertFalse( $dotdot['success'], 'a ../ path out of uploads is refused too' );
	}

	public function test_stream_wrappers_and_empty_sources_are_refused(): void {
		$this->assertFalse( $this->info( 'phar:///tmp/x.phar/a.png' )['success'] );
		$this->assertFalse( $this->info( 'php://filter/resource=/etc/passwd' )['success'] );
		$this->assertFalse( $this->info( '' )['success'] );
	}
}
