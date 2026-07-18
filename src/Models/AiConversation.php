<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiMessageRole;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\AiConversationFactory;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $title
 * @property string $aspect
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiMessage> $messages
 */
class AiConversation extends Model
{
	/** @use HasFactory<AiConversationFactory> */
	use HasFactory;

	protected $table = 'switchbot_ai_conversations';

	protected $fillable = [
		'user_id',
		'title',
		'aspect',
	];

	protected static function newFactory(): AiConversationFactory
	{
		return AiConversationFactory::new();
	}

	/** @return HasMany<AiMessage, $this> */
	public function messages(): HasMany
	{
		return $this->hasMany(AiMessage::class)->orderBy('id');
	}

	/** The most recent assistant message that produced a ready image. */
	public function latestImageMessage(): ?AiMessage
	{
		return $this->messages()
			->where('role', AiMessageRole::Assistant)
			->where('status', AiImageStatus::Ready)
			->whereNotNull('image_path')
			->reorder('id', 'desc')
			->first();
	}

	/** Whether any message in the conversation is still generating. */
	public function isProcessing(): bool
	{
		return $this->messages()
			->where('status', AiImageStatus::Processing)
			->exists();
	}

	/** Sum of the (experimental) estimated cost of every message, or null when none is set. */
	public function estimatedCost(): ?float
	{
		$sum = (float) $this->messages()->whereNotNull('cost_usd')->sum('cost_usd');

		return $sum > 0 ? $sum : null;
	}
}
