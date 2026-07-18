<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\Gallery;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\OptimizeFrameImageJob;

class GalleryTest extends TestCase
{
	use RefreshDatabase;

	protected bool $commandsFail = false;

	protected ?int $commandsHttpStatus = null;

	protected function setUp(): void
	{
		parent::setUp();

		config([
			'switchbot.token' => 'test-token',
			'switchbot.secret' => 'test-secret',
			'switchbot.device_id' => 'FRAME123456',
		]);

		// Covers the nested NowShowing status poll and any command calls. A single
		// closure fake avoids stub-ordering surprises; $commandsFail returns an
		// application-level error and $commandsHttpStatus a real HTTP failure.
		Http::fake(function (Request $request) {
			if (str_contains($request->url(), '/commands')) {
				if ($this->commandsHttpStatus !== null) {
					return Http::response('{"statusCode":413,"message":"Request body is too large"}', $this->commandsHttpStatus);
				}

				if ($this->commandsFail) {
					return Http::response(['statusCode' => 190, 'message' => 'Device internal error']);
				}
			}

			return Http::response([
				'statusCode' => 100,
				'message' => 'success',
				'body' => ['battery' => 0, 'onlineStatus' => 'online', 'imageUrl' => 'https://frames.example.com/a.jpg'],
			]);
		});

		// The push hands SwitchBot a temporary URL; give the fake disk a generator.
		Storage::disk('s3')->buildTemporaryUrlsUsing(
			fn (string $path, $expiration, array $options = []): string => 'https://frames.example.test/' . $path,
		);
	}

	public function test_it_stores_an_uploaded_image_optimized_for_the_panel(): void
	{
		Livewire::test(Gallery::class)
			->set('photo', UploadedFile::fake()->image('sunset.jpg', 800, 500))
			->set('title', 'Sunset over the IJ')
			->call('save')
			->assertHasNoErrors();

		$image = FrameImage::sole();

		$this->assertSame('Sunset over the IJ', $image->title);
		$this->assertSame(1600, $image->width);
		$this->assertSame(1200, $image->height);
		$this->assertTrue($image->optimized);
		$this->assertSame(FrameImageStatus::Ready, $image->status);

		Storage::disk('s3')->assertExists($image->path);
	}

	public function test_it_queues_optimization_and_marks_the_image_processing(): void
	{
		Queue::fake();

		Livewire::test(Gallery::class)
			->set('photo', UploadedFile::fake()->image('sunset.jpg', 800, 500))
			->call('save')
			->assertHasNoErrors();

		Queue::assertPushed(OptimizeFrameImageJob::class);

		$image = FrameImage::sole();

		$this->assertSame(FrameImageStatus::Processing, $image->status);
		$this->assertNull($image->width);
		$this->assertNotEmpty(Storage::disk('s3')->files('switchbot/frame-images/incoming'));
	}

	public function test_it_flashes_an_error_when_the_push_to_the_frame_fails(): void
	{
		$this->commandsFail = true;

		$image = FrameImage::factory()->create();
		Storage::disk('s3')->put($image->path, 'fake-jpeg-bytes');

		Livewire::test(Gallery::class)
			->call('pushToFrame', $image->id)
			->assertNotDispatched('frame-updated')
			->assertSet('noticeType', 'error')
			->assertSet('notice', fn (?string $notice): bool => filled($notice));

		$image->refresh();

		$this->assertSame(0, $image->push_count);
		$this->assertNull($image->last_pushed_at);
	}

	public function test_it_will_not_push_an_image_that_is_still_processing(): void
	{
		$image = FrameImage::factory()->processing()->create();

		Livewire::test(Gallery::class)
			->call('pushToFrame', $image->id)
			->assertNotDispatched('frame-updated')
			->assertSet('noticeType', 'error');

		Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'commands'));
	}

	public function test_it_stores_portrait_uploads_in_portrait_orientation(): void
	{
		Livewire::test(Gallery::class)
			->set('photo', UploadedFile::fake()->image('tower.jpg', 500, 800))
			->call('save')
			->assertHasNoErrors();

		$image = FrameImage::sole();

		$this->assertSame(1200, $image->width);
		$this->assertSame(1600, $image->height);
	}

	public function test_it_requires_a_valid_image_upload(): void
	{
		Livewire::test(Gallery::class)
			->set('photo', UploadedFile::fake()->create('document.pdf', 100))
			->call('save')
			->assertHasErrors(['photo' => 'image']);
	}

	public function test_it_pushes_a_library_image_to_the_frame_as_a_url(): void
	{
		$image = FrameImage::factory()->create();
		Storage::disk('s3')->put($image->path, 'fake-jpeg-bytes');

		Livewire::test(Gallery::class)
			->call('pushToFrame', $image->id)
			->assertDispatched('frame-updated');

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'FRAME123456/commands')
			&& $request['command'] === 'uploadImage'
			&& $request['parameter']['imageUrl'] === 'https://frames.example.test/' . $image->path);

		$image->refresh();

		$this->assertSame(1, $image->push_count);
		$this->assertNotNull($image->last_pushed_at);
	}

	public function test_it_surfaces_a_payload_too_large_error_without_crashing(): void
	{
		$this->commandsHttpStatus = 413;

		$image = FrameImage::factory()->create();
		Storage::disk('s3')->put($image->path, 'fake-jpeg-bytes');

		Livewire::test(Gallery::class)
			->call('pushToFrame', $image->id)
			->assertNotDispatched('frame-updated')
			->assertSet('noticeType', 'error')
			->assertSet('notice', fn (?string $notice): bool => str_contains((string) $notice, '413'));

		$this->assertSame(0, $image->refresh()->push_count);
	}

	public function test_it_deletes_an_image_from_library_and_disk(): void
	{
		$image = FrameImage::factory()->create();
		Storage::disk('s3')->put($image->path, 'fake-jpeg-bytes');

		Livewire::test(Gallery::class)->call('deleteImage', $image->id);

		Storage::disk('s3')->assertMissing($image->path);
		$this->assertSame(0, FrameImage::count());
	}
}
