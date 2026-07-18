<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Unit;

use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameAspect;

class FrameAspectTest extends TestCase
{
	public function test_it_maps_each_orientation_to_a_tailwind_aspect_class(): void
	{
		$this->assertSame('aspect-[3/4]', FrameAspect::Portrait->cssClass());
		$this->assertSame('aspect-[4/3]', FrameAspect::Landscape->cssClass());
		$this->assertSame('aspect-square', FrameAspect::Square->cssClass());
	}

	public function test_it_resolves_from_a_config_value(): void
	{
		$this->assertSame(FrameAspect::Portrait, FrameAspect::fromValue('portrait'));
		$this->assertSame(FrameAspect::Landscape, FrameAspect::fromValue('landscape'));
		$this->assertSame(FrameAspect::Square, FrameAspect::fromValue('square'));
	}

	public function test_it_falls_back_to_portrait_for_unknown_or_missing_values(): void
	{
		$this->assertSame(FrameAspect::Portrait, FrameAspect::fromValue('nonsense'));
		$this->assertSame(FrameAspect::Portrait, FrameAspect::fromValue(''));
		$this->assertSame(FrameAspect::Portrait, FrameAspect::fromValue(null));
	}
}
