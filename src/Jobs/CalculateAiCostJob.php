<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SpaanProductions\LaravelSwitchbotFrame\Support\ModelPrices;
use SpaanProductions\LaravelSwitchbotFrame\Contracts\EstimatesAiCost;

/**
 * Estimates the (experimental) USD cost of a generation or prompt improvement
 * off the request, so the price-sheet fetch never blocks the user. Dispatched
 * only when cost estimation is enabled in config.
 */
class CalculateAiCostJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public int $timeout = 30;

	public int $tries = 1;

	/** @param EstimatesAiCost&\Illuminate\Database\Eloquent\Model $costable */
	public function __construct(public EstimatesAiCost $costable)
	{
	}

	public function handle(): void
	{
		$model = $this->costable->priceModel();

		if ($model === null) {
			return;
		}

		$cost = resolve(ModelPrices::class)->costFor(
			$model,
			$this->costable->priceInputTokens(),
			$this->costable->priceOutputTokens(),
		);

		if ($cost !== null) {
			$this->costable->applyEstimatedCost($cost);
		}
	}
}
