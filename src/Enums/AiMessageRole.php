<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Enums;

enum AiMessageRole: string
{
	case User = 'user';
	case Assistant = 'assistant';

	public function label(): string
	{
		return match ($this) {
			self::User => 'You',
			self::Assistant => 'Studio',
		};
	}
}
