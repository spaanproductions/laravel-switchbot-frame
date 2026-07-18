<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Contracts\EstimatesAiCost;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\AiPromptImprovementFactory;

/**
 * A logged "improve prompt with AI" call, kept for cost/usage tracking.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $input_prompt
 * @property string $output_prompt
 * @property string|null $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property float|null $cost_usd
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AiPromptImprovement extends Model implements EstimatesAiCost
{
	/** @use HasFactory<AiPromptImprovementFactory> */
	use HasFactory;

	protected $table = 'switchbot_ai_prompt_improvements';

	protected $fillable = [
		'user_id',
		'input_prompt',
		'output_prompt',
		'model',
		'input_tokens',
		'output_tokens',
		'cost_usd',
	];

	protected static function newFactory(): AiPromptImprovementFactory
	{
		return AiPromptImprovementFactory::new();
	}

	/** @return array<string, string> */
	protected function casts(): array
	{
		return [
			'cost_usd' => 'float',
		];
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
}
