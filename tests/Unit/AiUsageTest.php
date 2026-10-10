<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Unit;

use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Support\AiUsage;

class AiUsageTest extends TestCase
{
	public function test_it_reads_the_laravel_ai_1_x_shape(): void
	{
		$usage = (object) ['inputTokens' => 120, 'outputTokens' => 30];

		$this->assertSame(['input_tokens' => 120, 'output_tokens' => 30], AiUsage::columns($usage));
	}

	public function test_it_reads_the_laravel_ai_0_x_shape(): void
	{
		$usage = (object) ['promptTokens' => 120, 'completionTokens' => 30];

		$this->assertSame(['input_tokens' => 120, 'output_tokens' => 30], AiUsage::columns($usage));
	}

	public function test_unreported_zero_counts_become_null(): void
	{
		$usage = (object) ['inputTokens' => 0, 'outputTokens' => 0];

		$this->assertSame(['input_tokens' => null, 'output_tokens' => null], AiUsage::columns($usage));
	}

	public function test_an_unknown_shape_yields_nulls(): void
	{
		$this->assertSame(['input_tokens' => null, 'output_tokens' => null], AiUsage::columns((object) []));
	}
}
