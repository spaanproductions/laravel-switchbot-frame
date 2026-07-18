<?php

namespace SpaanProductions\LaravelSwitchbotFrame;

use Livewire\Livewire;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\Gallery;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\NowShowing;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\WebhookManager;
use SpaanProductions\LaravelSwitchbotFrame\Console\Commands\ManageWebhook;
use SpaanProductions\LaravelSwitchbotFrame\Http\Controllers\ImageController;
use SpaanProductions\LaravelSwitchbotFrame\Http\Controllers\WebhookController;
use SpaanProductions\LaravelSwitchbotFrame\Http\Controllers\StylesheetController;

class LaravelSwitchbotFrameServiceProvider extends PackageServiceProvider
{
	public function configurePackage(Package $package): void
	{
		$package
			->name('laravel-switchbot-frame')
			->hasConfigFile('switchbot')
			->hasViews('switchbot')
			->hasCommand(ManageWebhook::class);
	}

	public function packageBooted(): void
	{
		$this->registerLivewireComponents();
		$this->registerRoutes();
		$this->registerPublishables();
	}

	private function registerLivewireComponents(): void
	{
		Livewire::component('switchbot.gallery', Gallery::class);
		Livewire::component('switchbot.now-showing', NowShowing::class);
		Livewire::component('switchbot.webhook-manager', WebhookManager::class);
	}

	private function registerRoutes(): void
	{
		$routes = (array) config('switchbot.routes');

		Route::prefix($routes['prefix'] ?? 'switchbot')
			->middleware($routes['middleware'] ?? ['web'])
			->group(function (): void {
				Route::livewire('/', Gallery::class)->name('switchbot.index');
				Route::get('/images/{frameImage}', ImageController::class)->name('switchbot.images.show');
				Route::get('/assets/app.css', StylesheetController::class)->name('switchbot.assets.css');
			});

		$webhook = (array) ($routes['webhook'] ?? []);

		Route::prefix($webhook['prefix'] ?? '')
			->middleware($webhook['middleware'] ?? ['api'])
			->group(function (): void {
				Route::post('switchbot/webhook/{token}', WebhookController::class)->name('switchbot.webhook');
			});
	}

	private function registerPublishables(): void
	{
		if ( ! $this->app->runningInConsole()) {
			return;
		}

		// The migrations are NOT auto-loaded — publish them so the host owns them
		// (Laravel's recommended approach). vendor:publish stamps a fresh timestamp.
		$this->publishesMigrations([
			__DIR__ . '/../database/migrations' => database_path('migrations'),
		], 'switchbot-frame-migrations');

		$this->publishes([
			__DIR__ . '/../dist/switchbot-frame.css' => public_path('vendor/switchbot/app.css'),
		], 'switchbot-frame-assets');

		// hasViews() already registers a publish under "switchbot-views"; add an
		// explicitly named group so every tag follows the "switchbot-frame-*" scheme.
		$this->publishes([
			__DIR__ . '/../resources/views' => resource_path('views/vendor/switchbot'),
		], 'switchbot-frame-views');
	}
}
