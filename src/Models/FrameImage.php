<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\FrameImageFactory;

/**
 * @property int $id
 * @property string|null $title
 * @property string $original_name
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $file_size
 * @property bool $optimized
 * @property FrameImageStatus $status
 * @property string|null $error
 * @property int $push_count
 * @property \Illuminate\Support\Carbon|null $last_pushed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $url
 * @property-read string $readable_file_size
 */
class FrameImage extends Model
{
	/** @use HasFactory<FrameImageFactory> */
	use HasFactory;

	protected $fillable = [
		'title',
		'original_name',
		'path',
		'width',
		'height',
		'file_size',
		'optimized',
		'status',
		'error',
	];

	protected static function newFactory(): FrameImageFactory
	{
		return FrameImageFactory::new();
	}

	/** @return array<string, string> */
	protected function casts(): array
	{
		return [
			'optimized' => 'boolean',
			'status' => FrameImageStatus::class,
			'last_pushed_at' => 'datetime',
		];
	}

	public function isReady(): bool
	{
		return $this->status === FrameImageStatus::Ready;
	}

	public function isProcessing(): bool
	{
		return $this->status === FrameImageStatus::Processing;
	}

	public function isFailed(): bool
	{
		return $this->status === FrameImageStatus::Failed;
	}

	/** The disk the image is stored on. */
	public function disk(): string
	{
		return config('switchbot.disk');
	}

	/** The admin route that streams this image to the browser. */
	protected function url(): Attribute
	{
		return Attribute::get(fn (): string => route('switchbot.images.show', $this));
	}

	/** Human readable file size, e.g. "1.2 MB". */
	protected function readableFileSize(): Attribute
	{
		return Attribute::get(fn (): string => Number::fileSize($this->file_size, precision: 1));
	}

	/**
	 * A short-lived, publicly fetchable URL for the SwitchBot uploadImage command.
	 *
	 * SwitchBot's cloud downloads the image from this URL, so it avoids the
	 * request-body size limit that base64 uploads hit. A presigned S3 URL works
	 * even with the bucket locked down, and is reachable by SwitchBot regardless
	 * of where the app runs (it points at S3, not this app).
	 */
	public function temporaryUrl(int $minutes = 15): string
	{
		return Storage::disk($this->disk())->temporaryUrl($this->path, now()->addMinutes($minutes));
	}

	/** The stored image as a base64 data URI (fallback for the SwitchBot uploadImage command). */
	public function toDataUri(): string
	{
		$contents = Storage::disk($this->disk())->get($this->path);

		$mimeType = Storage::disk($this->disk())->mimeType($this->path) ?: 'image/jpeg';

		return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
	}

	public function markPushed(): void
	{
		$this->increment('push_count', 1, ['last_pushed_at' => now()]);
	}
}
