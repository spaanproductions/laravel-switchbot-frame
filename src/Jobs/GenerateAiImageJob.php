<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Jobs;

use Throwable;
use Laravel\Ai\Image;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\ImageResponse;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Ai\Files\Image as ImageFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SpaanProductions\LaravelSwitchbotFrame\Support\AiUsage;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Support\ImageStudio;

/**
 * Generates (or edits) an image for an assistant message via laravel/ai.
 *
 * The provider call can take many seconds, so it runs off the request just like
 * the e-ink optimizer does. When a source image is provided (the previous turn's
 * image, or a turn-1 upload) it is attached so the provider edits it rather than
 * generating from scratch. Auth tokens come from laravel/ai's own configuration.
 */
class GenerateAiImageJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public int $timeout = 120;

	public int $tries = 1;

	public function __construct(
		public AiMessage $message,
		public string $prompt,
		public ?string $sourcePath = null,
	) {
	}

	public function handle(): void
	{
		$disk = Storage::disk(config('switchbot.disk'));

		try {
			$response = $this->generate();

			$bytes = (string) $response;

			$path = 'switchbot/ai-conversations/' . $this->message->ai_conversation_id . '/' . Str::uuid() . '.png';

			$disk->put($path, $bytes, config('switchbot.disk_visibility') ?: []);

			$dimensions = @getimagesizefromstring($bytes);

			$this->message->update([
				'image_path' => $path,
				'width' => is_array($dimensions) ? $dimensions[0] : null,
				'height' => is_array($dimensions) ? $dimensions[1] : null,
				'file_size' => $disk->size($path),
				...$this->bookkeeping($response),
				'status' => AiImageStatus::Ready,
				'error' => null,
			]);
		} catch (Throwable $e) {
			$this->message->update([
				'status' => AiImageStatus::Failed,
				'error' => $e->getMessage(),
			]);

			return;
		}

		if (ImageStudio::costEstimationEnabled()) {
			// Cost is an indication only; failing to queue it must never fail the image.
			rescue(fn () => Bus::dispatch(new CalculateAiCostJob($this->message)), report: false);
		}
	}

	/**
	 * Token usage and model are bookkeeping only: if reading them fails (e.g. a
	 * laravel/ai shape change), the image is still marked ready without them.
	 *
	 * @return array{input_tokens: int|null, output_tokens: int|null, model: string|null}
	 */
	private function bookkeeping(ImageResponse $response): array
	{
		return [
			...rescue(fn () => AiUsage::columns($response->usage), ['input_tokens' => null, 'output_tokens' => null], false),
			'model' => rescue(fn () => $response->meta->model, null, false),
		];
	}

	private function generate(): ImageResponse
	{
		$pending = Image::of($this->prompt);

		if ($this->sourcePath !== null) {
			$pending->attachments([
				ImageFile::fromStorage($this->sourcePath, config('switchbot.disk')),
			]);
		}

		$pending = match ($this->aspect()) {
			'portrait' => $pending->portrait(),
			'square' => $pending->square(),
			default => $pending->landscape(),
		};

		return $pending->generate(
			provider: ImageStudio::provider(),
			model: ImageStudio::model(),
		);
	}

	private function aspect(): string
	{
		return $this->message->conversation->aspect;
	}
}
