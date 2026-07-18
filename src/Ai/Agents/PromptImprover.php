<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Ai\Agents;

use Stringable;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use SpaanProductions\LaravelSwitchbotFrame\Support\ImageStudio;

/**
 * Rewrites a user's short prompt into a richer image-generation prompt.
 *
 * Prompt improvement is a text task, so it uses laravel/ai's default text
 * provider (config/ai.php `default`) unless overridden via the `ai.improve` config.
 */
class PromptImprover implements Agent
{
	use Promptable;

	public function instructions(): Stringable|string
	{
		return 'You refine prompts for an AI image generator that creates art for a digital picture frame. '
			. 'Rewrite the user\'s prompt into a single vivid image prompt that keeps their intent while adding tasteful '
			. 'detail about subject, style, lighting, mood and composition. Keep it under 80 words. Return ONLY the '
			. 'improved prompt text — no preamble, quotes, labels or explanation.';
	}

	public function provider(): Lab|array|string|null
	{
		return ImageStudio::improveProvider();
	}

	public function model(): ?string
	{
		return ImageStudio::improveModel();
	}
}
