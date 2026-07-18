<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Unit;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Actions\OptimizeImageForEink;

class OptimizeImageForEinkTest extends TestCase
{
	protected function tearDown(): void
	{
		foreach (glob(sys_get_temp_dir() . '/eink-test*') as $file) {
			@unlink($file);
		}

		parent::tearDown();
	}

	private function makeJpeg(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);
		imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 120, 80, 200));

		$path = tempnam(sys_get_temp_dir(), 'eink-test') . '.jpg';
		imagejpeg($image, $path);

		return $path;
	}

	public function test_it_resizes_landscape_images_to_1600x1200(): void
	{
		$source = $this->makeJpeg(801, 500);
		$target = tempnam(sys_get_temp_dir(), 'eink-test');

		$dimensions = (new OptimizeImageForEink)->handle($source, $target);

		$this->assertSame(['width' => 1600, 'height' => 1200], $dimensions);
		$this->assertSame([1600, 1200], array_slice(getimagesize($target), 0, 2));
	}

	public function test_it_resizes_portrait_images_to_1200x1600(): void
	{
		$source = $this->makeJpeg(500, 801);
		$target = tempnam(sys_get_temp_dir(), 'eink-test');

		$dimensions = (new OptimizeImageForEink)->handle($source, $target, enhance: false);

		$this->assertSame(['width' => 1200, 'height' => 1600], $dimensions);
	}

	public function test_it_rejects_files_that_are_not_images(): void
	{
		$source = tempnam(sys_get_temp_dir(), 'eink-test');
		file_put_contents($source, 'definitely not an image');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('not a valid image');

		(new OptimizeImageForEink)->handle($source, $source . '.out');
	}

	public function test_it_throws_when_the_source_file_cannot_be_read(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not read image');

		(new OptimizeImageForEink)->handle('/nonexistent/path/nope.jpg', tempnam(sys_get_temp_dir(), 'eink-test'));
	}

	public function test_it_throws_when_the_target_cannot_be_written(): void
	{
		$source = $this->makeJpeg(801, 500);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not write optimized image');

		(new OptimizeImageForEink)->handle($source, '/nonexistent-dir/out.jpg');
	}

	/**
	 * A real EXIF-orientation test was not feasible: plain GD cannot write an
	 * EXIF Orientation tag, and we do not fake/monkeypatch exif. This instead
	 * proves applyExifOrientation() is a safe no-op when the source has no EXIF.
	 */
	public function test_it_leaves_images_without_exif_orientation_unchanged(): void
	{
		$source = $this->makeJpeg(801, 500);
		$target = tempnam(sys_get_temp_dir(), 'eink-test');

		$dimensions = (new OptimizeImageForEink)->handle($source, $target);

		$this->assertSame(['width' => 1600, 'height' => 1200], $dimensions);
		$this->assertSame([1600, 1200], array_slice(getimagesize($target), 0, 2));
	}
}
