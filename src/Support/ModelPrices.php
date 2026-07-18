<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Looks up approximate per-token model prices from LiteLLM's public price sheet
 * (model_prices_and_context_window.json).
 *
 * The sheet is fetched over HTTP and cached; it is re-fetched once the cache
 * expires (every `cost_estimation.refresh_interval` hours). All figures are a
 * rough INDICATION only — see the config docs — never rely on them for billing.
 */
class ModelPrices
{
	private const string CACHE_KEY = 'switchbot-ai-model-prices';

	/** Estimate the USD cost of a call, or null when the model/prices are unknown. */
	public function costFor(string $model, int $inputTokens, int $outputTokens): ?float
	{
		$entry = $this->entryFor($model);

		if ($entry === null) {
			return null;
		}

		$inputRate = $entry['input_cost_per_token'] ?? null;
		$outputRate = $entry['output_cost_per_token'] ?? null;

		if ($inputRate === null && $outputRate === null) {
			return null;
		}

		return ($inputTokens * (float) ($inputRate ?? 0)) + ($outputTokens * (float) ($outputRate ?? 0));
	}

	/**
	 * Find the price entry for a model, tolerating LiteLLM's "provider/model" keys.
	 *
	 * @return array<string, mixed>|null
	 */
	private function entryFor(string $model): ?array
	{
		$sheet = $this->sheet();

		if (isset($sheet[$model]) && is_array($sheet[$model])) {
			return $sheet[$model];
		}

		foreach ($sheet as $key => $entry) {
			if (is_array($entry) && str_ends_with((string) $key, '/' . $model)) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * The cached price sheet. Only successful responses are cached, so a failed
	 * fetch is retried on the next call rather than caching an empty sheet.
	 *
	 * @return array<string, mixed>
	 */
	private function sheet(): array
	{
		$cached = Cache::get(self::CACHE_KEY);

		if (is_array($cached)) {
			return $cached;
		}

		$response = Http::timeout(15)->get((string) config('switchbot.ai.cost_estimation.prices_url'));

		if ( ! $response->successful()) {
			return [];
		}

		$sheet = (array) $response->json();

		$hours = max(1, (int) config('switchbot.ai.cost_estimation.refresh_interval', 24));

		Cache::put(self::CACHE_KEY, $sheet, now()->addHours($hours));

		return $sheet;
	}
}
