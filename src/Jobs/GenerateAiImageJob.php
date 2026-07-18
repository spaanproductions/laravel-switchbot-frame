<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Jobs;

use Throwable;
use Laravel\Ai\Image;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\ImageResponse;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Ai\Files\Image as ImageFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
				'input_tokens' => $response->usage->promptTokens ?: null,
				'output_tokens' => $response->usage->completionTokens ?: null,
				'model' => $response->meta->model,
				'status' => AiImageStatus::Ready,
				'error' => null,
			]);

			if (ImageStudio::costEstimationEnabled()) {
				CalculateAiCostJob::dispatch($this->message);
			}
		} catch (Throwable $e) {
			$this->message->update([
				'status' => AiImageStatus::Failed,
				'error' => $e->getMessage(),
			]);
		}
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
