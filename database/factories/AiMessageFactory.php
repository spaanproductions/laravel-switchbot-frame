<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiMessageRole;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
	protected $model = AiMessage::class;

	public function definition(): array
	{
		return [
			'ai_conversation_id' => AiConversation::factory(),
			'role' => AiMessageRole::User,
			'prompt' => fake()->sentence(),
			'image_path' => null,
			'width' => null,
			'height' => null,
			'file_size' => null,
			'status' => AiImageStatus::Ready,
			'error' => null,
		];
	}

	public function assistant(): static
	{
		return $this->state(fn (): array => [
			'role' => AiMessageRole::Assistant,
			'prompt' => null,
			'image_path' => 'switchbot/ai-conversations/' . fake()->numberBetween(1, 99) . '/' . fake()->uuid() . '.png',
			'width' => 1536,
			'height' => 1024,
			'file_size' => fake()->numberBetween(100_000, 900_000),
			'status' => AiImageStatus::Ready,
		]);
	}

	public function processing(): static
	{
		return $this->state(fn (): array => [
			'role' => AiMessageRole::Assistant,
			'prompt' => null,
			'status' => AiImageStatus::Processing,
			'image_path' => null,
		]);
	}

	public function failed(): static
	{
		return $this->state(fn (): array => [
			'role' => AiMessageRole::Assistant,
			'prompt' => null,
			'status' => AiImageStatus::Failed,
			'error' => 'Generation failed.',
			'image_path' => null,
		]);
	}
}
