<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
	protected $model = AiConversation::class;

	public function definition(): array
	{
		return [
			'user_id' => null,
			'title' => fake()->sentence(4),
			'aspect' => 'landscape',
		];
	}
}
