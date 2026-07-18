<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;
use SpaanProductions\LaravelSwitchbotFrame\Support\ModelPrices;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\CalculateAiCostJob;

class AiCostEstimationTest extends TestCase
{
	use RefreshDatabase;

	private function fakePriceSheet(): void
	{
		Http::fake([
			'raw.githubusercontent.com/*' => Http::response([
				'sample_spec' => ['note' => 'ignored'],
				'gpt-4o' => ['input_cost_per_token' => 0.0000025, 'output_cost_per_token' => 0.00001],
				'gemini/gemini-2.5-flash-image' => ['input_cost_per_token' => 0.0000003, 'output_cost_per_token' => 0.000002],
			], 200),
		]);
	}

	public function test_it_estimates_cost_from_per_token_prices(): void
	{
		$this->fakePriceSheet();

		$prices = new ModelPrices;

		$this->assertEqualsWithDelta(
			(1000 * 0.0000025) + (500 * 0.00001),
			$prices->costFor('gpt-4o', 1000, 500),
			1e-9,
		);
	}

	public function test_it_resolves_provider_prefixed_price_keys(): void
	{
		$this->fakePriceSheet();

		$cost = (new ModelPrices)->costFor('gemini-2.5-flash-image', 100, 50);

		$this->assertEqualsWithDelta((100 * 0.0000003) + (50 * 0.000002), $cost, 1e-9);
	}

	public function test_it_returns_null_for_unknown_models(): void
	{
		$this->fakePriceSheet();

		$this->assertNull((new ModelPrices)->costFor('totally-unknown-model', 100, 50));
	}

	public function test_the_cost_job_stores_the_estimate_on_a_message(): void
	{
		$this->fakePriceSheet();

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'model' => 'gpt-4o',
			'input_tokens' => 100,
			'output_tokens' => 50,
			'cost_usd' => null,
		]);

		(new CalculateAiCostJob($message))->handle();

		$this->assertEqualsWithDelta(
			(100 * 0.0000025) + (50 * 0.00001),
			(float) $message->fresh()->cost_usd,
			1e-9,
		);
	}

	public function test_the_cost_job_leaves_cost_null_for_unknown_models(): void
	{
		$this->fakePriceSheet();

		$message = AiMessage::factory()->assistant()->create([
			'model' => 'mystery-model',
			'input_tokens' => 10,
			'output_tokens' => 5,
			'cost_usd' => null,
		]);

		(new CalculateAiCostJob($message))->handle();

		$this->assertNull($message->fresh()->cost_usd);
	}
}
