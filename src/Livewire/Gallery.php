<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotApi;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameAspect;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotException;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\OptimizeFrameImageJob;

#[Layout('switchbot::layout')]
class Gallery extends Component
{
	use WithFileUploads;

	#[Validate(['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'])]
	public ?TemporaryUploadedFile $photo = null;

	public string $title = '';

	public bool $enhance = true;

	public ?string $notice = null;

	public string $noticeType = 'success';

	public function save(): void
	{
		$this->validate([
			'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
			'title' => ['nullable', 'string', 'max:120'],
		]);

		$disk = config('switchbot.disk');
		$extension = $this->photo->getClientOriginalExtension() ?: 'jpg';
		$incomingPath = 'switchbot/frame-images/incoming/' . Str::uuid() . '.' . $extension;

		// Read via ->get() rather than getRealPath(): the latter returns false when
		// Livewire's temporary-upload disk is not local (e.g. S3), which would break
		// the copy. See the LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK note in the docs.
		Storage::disk($disk)->put(
			$incomingPath,
			$this->photo->get(),
			config('switchbot.disk_visibility') ?: [],
		);

		$image = FrameImage::create([
			'title' => $this->title !== '' ? $this->title : null,
			'original_name' => $this->photo->getClientOriginalName(),
			'path' => 'switchbot/frame-images/' . Str::uuid() . '.jpg',
			'optimized' => $this->enhance,
			'status' => FrameImageStatus::Processing,
		]);

		OptimizeFrameImageJob::dispatch($image, $incomingPath, $this->enhance);

		$this->reset('photo', 'title');
		$this->enhance = true;

		$this->flash('Image added — optimizing for the panel now.');
	}

	public function pushToFrame(int $imageId, SwitchBotApi $api): void
	{
		$image = FrameImage::findOrFail($imageId);

		if ( ! $image->isReady()) {
			$this->flash('That image is still being prepared.', 'error');

			return;
		}

		try {
			$api->uploadImageFromUrl($image->temporaryUrl());
		} catch (SwitchBotException $e) {
			$this->flash($e->getMessage(), 'error');

			return;
		}

		$image->markPushed();

		$this->dispatch('frame-updated');

		$this->flash('Sent to the frame. E-ink refresh takes a moment.');
	}

	public function deleteImage(int $imageId): void
	{
		$image = FrameImage::findOrFail($imageId);

		Storage::disk(config('switchbot.disk'))->delete($image->path);

		$image->delete();

		$this->flash('Image removed from your library.');
	}

	public function render(SwitchBotApi $api): View
	{
		return ViewFactory::make('switchbot::livewire.gallery', [
			'images' => FrameImage::query()->latest()->get(),
			'configured' => $api->isConfigured(),
			'dropzoneAspect' => FrameAspect::fromValue(config('switchbot.aspect.dropzone')),
		]);
	}

	private function flash(string $message, string $type = 'success'): void
	{
		$this->notice = $message;
		$this->noticeType = $type;

		$this->dispatch('notice-shown');
	}
}
