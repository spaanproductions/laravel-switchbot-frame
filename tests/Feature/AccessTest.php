<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\Fixtures\ForbidsAccess;

class AccessTest extends TestCase
{
	use RefreshDatabase;

	public function withoutAuth($app): void
	{
		$app['config']->set('switchbot.routes.middleware', ['web']);
	}

	public function useGuardMiddleware($app): void
	{
		$app['config']->set('switchbot.routes.middleware', ['web', ForbidsAccess::class]);
	}

	public function test_the_page_and_its_assets_are_guarded_by_auth_but_the_webhook_is_public(): void
	{
		$routes = $this->app['router']->getRoutes();

		$this->assertContains('auth:sanctum', $routes->getByName('switchbot.index')->gatherMiddleware());
		$this->assertContains('auth:sanctum', $routes->getByName('switchbot.images.show')->gatherMiddleware());
		$this->assertContains('auth:sanctum', $routes->getByName('switchbot.assets.css')->gatherMiddleware());
		$this->assertNotContains('auth:sanctum', $routes->getByName('switchbot.webhook')->gatherMiddleware());
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_gallery_page_renders(): void
	{
		$this->get(route('switchbot.index'))
			->assertOk()
			->assertSee('The Gallery');
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_the_stylesheet_route_serves_the_compiled_css(): void
	{
		$response = $this->get(route('switchbot.assets.css'));

		$response->assertOk();

		$this->assertStringContainsString('text/css', (string) $response->headers->get('Content-Type'));
		$this->assertStringContainsString('aspect-ratio', $response->getContent());
	}

	#[DefineEnvironment('withoutAuth')]
	public function test_a_published_stylesheet_overrides_the_packaged_one(): void
	{
		$published = public_path('vendor/switchbot/app.css');
		@mkdir(dirname($published), 0o777, true);
		file_put_contents($published, '/* published-marker */');

		try {
			// the route serves the published copy...
			$this->get(route('switchbot.assets.css'))
				->assertOk()
				->assertSee('published-marker', escape: false);

			// ...and the page links to the static asset instead of the route
			$this->get(route('switchbot.index'))
				->assertOk()
				->assertSee('vendor/switchbot/app.css', escape: false);
		} finally {
			@unlink($published);
			@rmdir(dirname($published));
			@rmdir(dirname(dirname($published)));
		}
	}

	#[DefineEnvironment('useGuardMiddleware')]
	public function test_the_configured_middleware_guards_the_page(): void
	{
		$this->get(route('switchbot.index'))->assertForbidden();
	}
}
