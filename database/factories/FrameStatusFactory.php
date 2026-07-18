<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameStatus;

/**
 * @extends Factory<FrameStatus>
 */
class FrameStatusFactory extends Factory
{
	protected $model = FrameStatus::class;

	public function definition(): array
	{
		return [
			'device_mac' => strtoupper(fake()->regexify('[0-9A-F]{12}')),
			'battery' => fake()->numberBetween(1, 100),
			'display_mode' => fake()->randomElement(['fill', 'fit', null]),
			'received_at' => now(),
		];
	}
}
