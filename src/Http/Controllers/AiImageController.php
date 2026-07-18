<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;

class AiImageController
{
	/** Stream a generated Studio image (kept private on the disk, super-admins only). */
	public function __invoke(AiMessage $aiMessage): StreamedResponse
	{
		abort_if($aiMessage->image_path === null, 404);

		abort_unless(Storage::disk($aiMessage->disk())->exists($aiMessage->image_path), 404);

		return Storage::disk($aiMessage->disk())->response($aiMessage->image_path);
	}
}
