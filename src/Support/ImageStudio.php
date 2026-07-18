<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Support;

use Laravel\Ai\Image;

/**
 * Feature gate for the optional AI Image Studio.
 *
 * The Studio only turns on when the "laravel/ai" package is installed (detected
 * via class_exists — importing the class only resolves a compile-time string, so
 * this stays safe when the package is absent) *and* it is enabled in config.
 */
class ImageStudio
{
	public static function installed(): bool
	{
		return class_exists(Image::class);
	}

	public static function enabled(): bool
	{
		return (bool) config('switchbot.ai.enabled', true) && static::installed();
	}

	public static function provider(): ?string
	{
		$provider = config('switchbot.ai.images.provider');

		return filled($provider) ? (string) $provider : null;
	}

	public static function model(): ?string
	{
		$model = config('switchbot.ai.images.model');

		return filled($model) ? (string) $model : null;
	}

	public static function improveProvider(): ?string
	{
		$provider = config('switchbot.ai.improve.provider');

		return filled($provider) ? (string) $provider : null;
	}

	public static function improveModel(): ?string
	{
		$model = config('switchbot.ai.improve.model');

		return filled($model) ? (string) $model : null;
	}

	public static function costEstimationEnabled(): bool
	{
		return (bool) config('switchbot.ai.cost_estimation.enabled', false);
	}

	/** @return array<string, array<string, mixed>> */
	public static function optimizerPresets(): array
	{
		$presets = config('switchbot.optimizer.presets', []);

		return is_array($presets) ? $presets : [];
	}

	public static function defaultAspect(): string
	{
		$aspect = config('switchbot.ai.images.default_aspect');

		// Fall back to the "now showing" frame orientation so generated art matches
		// the shape the frame is displaying.
		return filled($aspect)
			? (string) $aspect
			: (string) config('switchbot.aspect.now_showing', 'landscape');
	}
}
