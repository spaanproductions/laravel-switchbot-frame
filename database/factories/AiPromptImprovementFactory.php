<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiPromptImprovement;

/**
 * @extends Factory<AiPromptImprovement>
 */
class AiPromptImprovementFactory extends Factory
{
	protected $model = AiPromptImprovement::class;

	public function definition(): array
	{
		return [
			'user_id' => null,
			'input_prompt' => fake()->words(3, true),
			'output_prompt' => fake()->sentence(),
			'model' => 'gpt-4o',
			'input_tokens' => fake()->numberBetween(20, 200),
			'output_tokens' => fake()->numberBetween(20, 200),
			'cost_usd' => null,
		];
	}
}
