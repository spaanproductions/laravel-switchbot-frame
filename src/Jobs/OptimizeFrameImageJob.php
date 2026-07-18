<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Actions\OptimizeImageForEink;

/**
 * Optimizes a freshly uploaded image for the e-ink panel off the web request.
 *
 * The saturation boost is a per-pixel pass over ~1.9M pixels, far too slow to
 * run inside the Livewire upload request, so the raw upload is parked on the
 * disk and this job produces the final image and flips the row to Ready/Failed.
 */
class OptimizeFrameImageJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public int $timeout = 120;

	public int $tries = 1;

	/** @param array<string, mixed>|null $optimizer Preset boosts; null uses the configured defaults. */
	public function __construct(
		public FrameImage $image,
		public string $incomingPath,
		public bool $enhance = true,
		public ?array $optimizer = null,
	) {
	}

	public function handle(): void
	{
		$optimizer = $this->optimizer !== null
			? OptimizeImageForEink::fromArray($this->optimizer)
			: resolve(OptimizeImageForEink::class);

		$disk = Storage::disk(config('switchbot.disk'));

		$source = tempnam(sys_get_temp_dir(), 'frame-src');
		$target = tempnam(sys_get_temp_dir(), 'frame-opt');

		try {
			file_put_contents($source, $disk->get($this->incomingPath));

			$dimensions = $optimizer->handle($source, $target, $this->enhance);

			$disk->put($this->image->path, file_get_contents($target), config('switchbot.disk_visibility') ?: []);

			$this->image->update([
				'width' => $dimensions['width'],
				'height' => $dimensions['height'],
				'file_size' => $disk->size($this->image->path),
				'status' => FrameImageStatus::Ready,
				'error' => null,
			]);
		} catch (Throwable $e) {
			$this->image->update([
				'status' => FrameImageStatus::Failed,
				'error' => $e->getMessage(),
			]);
		} finally {
			@unlink($source);
			@unlink($target);
			$disk->delete($this->incomingPath);
		}
	}
}
