<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;

class ImageController
{
	/** Stream a stored frame image (kept private on the disk, super-admins only). */
	public function __invoke(FrameImage $frameImage): StreamedResponse
	{
		abort_unless(Storage::disk($frameImage->disk())->exists($frameImage->path), 404);

		return Storage::disk($frameImage->disk())->response($frameImage->path);
	}
}
