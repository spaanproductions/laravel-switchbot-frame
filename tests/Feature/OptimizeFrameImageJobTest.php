<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\OptimizeFrameImageJob;

class OptimizeFrameImageJobTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_optimizes_the_image_and_marks_it_ready(): void
	{
		$incoming = 'switchbot/frame-images/incoming/source.jpg';
		Storage::disk('s3')->put($incoming, $this->jpegBytes(800, 500));

		$image = FrameImage::factory()->processing()->create([
			'path' => 'switchbot/frame-images/final.jpg',
			'optimized' => true,
		]);

		(new OptimizeFrameImageJob($image, $incoming, true))->handle();

		$image->refresh();

		$this->assertSame(FrameImageStatus::Ready, $image->status);
		$this->assertSame(1600, $image->width);
		$this->assertSame(1200, $image->height);
		$this->assertGreaterThan(0, $image->file_size);
		$this->assertNull($image->error);

		Storage::disk('s3')->assertExists($image->path);
		Storage::disk('s3')->assertMissing($incoming);
	}

	public function test_it_marks_the_image_failed_when_the_source_is_not_an_image(): void
	{
		$incoming = 'switchbot/frame-images/incoming/bad.jpg';
		Storage::disk('s3')->put($incoming, 'this-is-not-an-image');

		$image = FrameImage::factory()->processing()->create();

		(new OptimizeFrameImageJob($image, $incoming, true))->handle();

		$image->refresh();

		$this->assertSame(FrameImageStatus::Failed, $image->status);
		$this->assertNotNull($image->error);

		Storage::disk('s3')->assertMissing($incoming);
	}

	private function jpegBytes(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);

		ob_start();
		imagejpeg($image);

		return (string) ob_get_clean();
	}
}
