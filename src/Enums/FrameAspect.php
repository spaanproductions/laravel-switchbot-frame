<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Enums;

/**
 * Orientation of the on-screen frame previews in the SwitchBot admin UI.
 *
 * Purely cosmetic — it controls the aspect ratio of the "Now showing" frame and
 * the upload dropzone, not how images are cropped for the panel. The three
 * possible Tailwind classes are safelisted in resources/css/app.css.
 */
enum FrameAspect: string
{
	case Portrait = 'portrait';
	case Landscape = 'landscape';
	case Square = 'square';

	public function label(): string
	{
		return match ($this) {
			self::Portrait => 'Portrait',
			self::Landscape => 'Landscape',
			self::Square => 'Square',
		};
	}

	/** Tailwind aspect-ratio utility for this orientation. */
	public function cssClass(): string
	{
		return match ($this) {
			self::Portrait => 'aspect-[3/4]',
			self::Landscape => 'aspect-[4/3]',
			self::Square => 'aspect-square',
		};
	}

	/** Resolve from a config value, defaulting to Portrait for anything unknown. */
	public static function fromValue(?string $value): self
	{
		return self::tryFrom($value ?? '') ?? self::Portrait;
	}
}
