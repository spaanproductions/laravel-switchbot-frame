<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\Gallery;

class AiStudioAccessTest extends TestCase
{
	use RefreshDatabase;

	public function disableStudio($app): void
	{
		$app['config']->set('switchbot.ai.enabled', false);
	}

	public function test_the_studio_is_available_by_default(): void
	{
		$routes = $this->app['router']->getRoutes();

		$this->assertTrue($routes->hasNamedRoute('switchbot.studio'));
		$this->assertTrue($routes->hasNamedRoute('switchbot.studio.image'));
	}

	public function test_the_gallery_links_to_the_studio(): void
	{
		Livewire::test(Gallery::class)->assertSee('Create with AI');
	}

	#[DefineEnvironment('disableStudio')]
	public function test_the_studio_can_be_disabled_via_config(): void
	{
		$routes = $this->app['router']->getRoutes();

		$this->assertFalse($routes->hasNamedRoute('switchbot.studio'));
		$this->assertFalse($routes->hasNamedRoute('switchbot.studio.image'));

		Livewire::test(Gallery::class)->assertDontSee('Create with AI');
	}
}
