<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\SwitchBotWebhookLog;

/**
 * @extends Factory<SwitchBotWebhookLog>
 */
class SwitchBotWebhookLogFactory extends Factory
{
	protected $model = SwitchBotWebhookLog::class;

	public function definition(): array
	{
		return [
			'payload' => [
				'eventType' => 'changeReport',
				'eventVersion' => '1',
				'context' => [
					'deviceType' => 'AI Art Frame',
					'deviceMac' => strtoupper(fake()->regexify('[0-9A-F]{12}')),
					'battery' => fake()->numberBetween(1, 100),
				],
			],
		];
	}
}
