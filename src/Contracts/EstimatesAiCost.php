<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Contracts;

/**
 * A record whose (experimental) USD cost can be estimated from token usage.
 */
interface EstimatesAiCost
{
	/** The model name to price against, or null when unknown. */
	public function priceModel(): ?string;

	public function priceInputTokens(): int;

	public function priceOutputTokens(): int;

	public function applyEstimatedCost(?float $costUsd): void;
}
