<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Support;

/**
 * Reads the token counts from a laravel/ai usage object, whichever version is installed.
 *
 * laravel/ai 0.x reports `promptTokens` / `completionTokens`; 1.x renamed them to
 * `inputTokens` / `outputTokens` (on `Usage`, `TextUsage` and `ImageUsage`).
 * Unreported (zero) counts are stored as null.
 */
final class AiUsage
{
	/** @return array{input_tokens: int|null, output_tokens: int|null} */
	public static function columns(object $usage): array
	{
		return [
			'input_tokens' => self::read($usage, 'inputTokens', 'promptTokens'),
			'output_tokens' => self::read($usage, 'outputTokens', 'completionTokens'),
		];
	}

	private static function read(object $usage, string $property, string $legacyProperty): ?int
	{
		foreach ([$property, $legacyProperty] as $name) {
			if (property_exists($usage, $name)) {
				return (int) $usage->{$name} ?: null;
			}
		}

		return null;
	}
}
