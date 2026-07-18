<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests;

use Illuminate\Support\Facades\Http;
use Livewire\LivewireServiceProvider;
use Illuminate\Support\Facades\Storage;
use BladeUI\Icons\BladeIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Eloquent\Factories\Factory;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use SpaanProductions\LaravelSwitchbotFrame\LaravelSwitchbotFrameServiceProvider;

class TestCase extends Orchestra
{
	protected function setUp(): void
	{
		parent::setUp();

		Factory::guessFactoryNamesUsing(
			fn (string $modelName): string => 'SpaanProductions\\LaravelSwitchbotFrame\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
		);

		Http::preventStrayRequests();
		Storage::fake('s3');
	}

	protected function getPackageProviders($app): array
	{
		return [
			LivewireServiceProvider::class,
			BladeIconsServiceProvider::class,
			BladeHeroiconsServiceProvider::class,
			LaravelSwitchbotFrameServiceProvider::class,
		];
	}

	protected function defineDatabaseMigrations(): void
	{
		// The provider no longer auto-loads migrations (hosts publish them), so
		// register them here for the test database.
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
	}

	protected function defineEnvironment($app): void
	{
		$app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');

		$app['config']->set('database.default', 'testing');
		$app['config']->set('database.connections.testing', [
			'driver' => 'sqlite',
			'database' => ':memory:',
			'prefix' => '',
		]);
	}
}
