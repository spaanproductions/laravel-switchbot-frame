<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Enums;

enum FrameImageStatus: string
{
	case Processing = 'processing';
	case Ready = 'ready';
	case Failed = 'failed';

	public function label(): string
	{
		return match ($this) {
			self::Processing => 'Processing',
			self::Ready => 'Ready',
			self::Failed => 'Failed',
		};
	}
}
