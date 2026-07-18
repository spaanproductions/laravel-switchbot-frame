<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Enums;

enum AiImageStatus: string
{
	case Processing = 'processing';
	case Ready = 'ready';
	case Failed = 'failed';

	public function label(): string
	{
		return match ($this) {
			self::Processing => 'Generating',
			self::Ready => 'Ready',
			self::Failed => 'Failed',
		};
	}
}
