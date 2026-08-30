<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;

class AssetsTest extends TestCase
{
	use RefreshDatabase;

	public function withoutAuth($app): void
	{
		$app['config']->set('switchbot.routes.middleware', ['web']);
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_script_route_serves_the_heic_interceptor(): void
	{
		$response = $this->get(route('switchbot.assets.js', ['asset' => 'app']));

		$response->assertOk();

		$this->assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
		$this->assertStringContainsString('SwitchbotFrame', $response->getContent());
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_script_route_serves_the_heic_converter(): void
	{
		$response = $this->get(route('switchbot.assets.js', ['asset' => 'heic2any']));

		$response->assertOk();

		$this->assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
		$this->assertGreaterThan(1000, strlen((string) $response->getContent()));
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_script_route_rejects_an_unknown_asset(): void
	{
		// The {asset} segment is whitelisted, so anything else must not match the route.
		$this->get('/switchbot/assets/js/nope')->assertNotFound();
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_a_published_script_overrides_the_packaged_one(): void
	{
		$published = public_path('vendor/switchbot/app.js');
		@mkdir(dirname($published), 0o777, true);
		file_put_contents($published, '/* published-marker */');

		try {
			// the route serves the published copy...
			$this->get(route('switchbot.assets.js', ['asset' => 'app']))
				->assertOk()
				->assertSee('published-marker', escape: false);

			// ...and the page links to the static asset instead of the route
			$this->get(route('switchbot.index'))
				->assertOk()
				->assertSee('vendor/switchbot/app.js', escape: false);
		} finally {
			@unlink($published);
			@rmdir(dirname($published));
			@rmdir(dirname(dirname($published)));
		}
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_page_loads_the_heic_interceptor_and_accepts_any_image(): void
	{
		$this->get(route('switchbot.index'))
			->assertOk()
			->assertSee('window.SwitchbotFrame', escape: false)
			->assertSee('assets/js/app', escape: false)
			->assertSee('accept="image/*"', escape: false);
	}
}
