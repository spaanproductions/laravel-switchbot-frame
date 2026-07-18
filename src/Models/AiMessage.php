<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiMessageRole;
use SpaanProductions\LaravelSwitchbotFrame\Contracts\EstimatesAiCost;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\AiMessageFactory;

/**
 * @property int $id
 * @property int $ai_conversation_id
 * @property AiMessageRole $role
 * @property string|null $prompt
 * @property string|null $image_path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $file_size
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $model
 * @property float|null $cost_usd
 * @property AiImageStatus $status
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $url
 * @property-read string $readable_file_size
 */
class AiMessage extends Model implements EstimatesAiCost
{
	/** @use HasFactory<AiMessageFactory> */
	use HasFactory;

	protected $table = 'switchbot_ai_messages';

	protected $fillable = [
		'ai_conversation_id',
		'role',
		'prompt',
		'image_path',
		'width',
		'height',
		'file_size',
		'input_tokens',
		'output_tokens',
		'model',
		'cost_usd',
		'status',
		'error',
	];

	protected static function newFactory(): AiMessageFactory
	{
		return AiMessageFactory::new();
	}

	/** @return array<string, string> */
	protected function casts(): array
	{
		return [
			'role' => AiMessageRole::class,
			'status' => AiImageStatus::class,
			'cost_usd' => 'float',
		];
	}

	/** @return BelongsTo<AiConversation, $this> */
	public function conversation(): BelongsTo
	{
		return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
	}

	public function isUser(): bool
	{
		return $this->role === AiMessageRole::User;
	}

	public function isAssistant(): bool
	{
		return $this->role === AiMessageRole::Assistant;
	}

	public function isReady(): bool
	{
		return $this->status === AiImageStatus::Ready;
	}

	public function isProcessing(): bool
	{
		return $this->status === AiImageStatus::Processing;
	}

	public function isFailed(): bool
	{
		return $this->status === AiImageStatus::Failed;
	}

	public function hasImage(): bool
	{
		return $this->image_path !== null && $this->isReady();
	}

	/** Total tokens the provider reported for this generation, if any. */
	public function totalTokens(): ?int
	{
		if ($this->input_tokens === null && $this->output_tokens === null) {
			return null;
		}

		return (int) $this->input_tokens + (int) $this->output_tokens;
	}

	public function priceModel(): ?string
	{
		return $this->model;
	}

	public function priceInputTokens(): int
	{
		return (int) $this->input_tokens;
	}

	public function priceOutputTokens(): int
	{
		return (int) $this->output_tokens;
	}

	public function applyEstimatedCost(?float $costUsd): void
	{
		$this->update(['cost_usd' => $costUsd]);
	}

	/** The disk conversation images are stored on. */
	public function disk(): string
	{
		return config('switchbot.disk');
	}

	/** The admin route that streams this message's image to the browser. */
	protected function url(): Attribute
	{
		return Attribute::get(fn (): ?string => $this->image_path === null
			? null
			: route('switchbot.studio.image', $this));
	}

	/** Human readable file size, e.g. "1.2 MB". */
	protected function readableFileSize(): Attribute
	{
		return Attribute::get(fn (): string => Number::fileSize((int) $this->file_size, precision: 1));
	}

	/**
	 * A short-lived, publicly fetchable URL — used when saving the generated image
	 * into the frame library so the optimizer can pull the bytes off the disk.
	 */
	public function temporaryUrl(int $minutes = 15): string
	{
		return Storage::disk($this->disk())->temporaryUrl($this->image_path, now()->addMinutes($minutes));
	}
}
