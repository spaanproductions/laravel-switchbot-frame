<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\WebhookManager;
use SpaanProductions\LaravelSwitchbotFrame\Models\SwitchBotWebhookLog;

class WebhookManagerTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		config([
			'switchbot.token' => 'test-token',
			'switchbot.secret' => 'test-secret',
			'switchbot.webhook_token' => 'secret-token',
			'switchbot.webhook_url' => null,
		]);
	}

	public function test_it_lists_registered_webhooks_on_mount(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['urls' => ['https://example.com/switchbot/webhook/secret-token']],
		])]);

		Livewire::test(WebhookManager::class)
			->assertSet('urls', ['https://example.com/switchbot/webhook/secret-token'])
			->assertSee('https://example.com/switchbot/webhook/secret-token');
	}

	public function test_it_registers_the_derived_receiver_url(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(WebhookManager::class)
			->call('register')
			->assertSet('noticeType', 'success');

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'setupWebhook')
			&& str_ends_with($request['url'], '/switchbot/webhook/secret-token'));
	}

	public function test_it_refuses_to_register_without_a_webhook_token(): void
	{
		config(['switchbot.webhook_token' => null]);

		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(WebhookManager::class)
			->call('register')
			->assertSet('noticeType', 'error');

		Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'setupWebhook'));
	}

	public function test_it_deletes_a_registered_webhook(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(WebhookManager::class)
			->call('delete', 'https://example.com/switchbot/webhook/secret-token')
			->assertSet('noticeType', 'success');

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'deleteWebhook')
			&& $request['url'] === 'https://example.com/switchbot/webhook/secret-token');
	}

	public function test_it_shows_and_clears_stored_webhook_events_when_logging_is_enabled(): void
	{
		config(['switchbot.log_webhooks' => true]);
		SwitchBotWebhookLog::factory()->count(3)->create();

		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(WebhookManager::class)
			->assertSee('Recent webhook bodies')
			->call('clearLog')
			->assertSet('noticeType', 'success');

		$this->assertSame(0, SwitchBotWebhookLog::query()->count());
	}
}
