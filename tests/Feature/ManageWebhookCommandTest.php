<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;

class ManageWebhookCommandTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		config([
			'switchbot.token' => 'test-token',
			'switchbot.secret' => 'test-secret',
			'switchbot.webhook_token' => 'secret-token',
			'switchbot.webhook_url' => 'https://example.com/switchbot/webhook/secret-token',
		]);
	}

	public function test_it_registers_the_configured_webhook_url(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		$this->artisan('switchbot:webhook register')->assertSuccessful();

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'setupWebhook')
			&& $request['url'] === 'https://example.com/switchbot/webhook/secret-token');
	}

	public function test_it_refuses_a_url_that_does_not_end_with_the_token(): void
	{
		config(['switchbot.webhook_url' => 'https://example.com/switchbot/webhook/mismatch']);

		$this->artisan('switchbot:webhook register')->assertFailed();

		Http::assertNothingSent();
	}

	public function test_it_lists_registered_webhooks(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['urls' => ['https://example.com/switchbot/webhook/secret-token']],
		])]);

		$this->artisan('switchbot:webhook list')
			->expectsOutputToContain('https://example.com/switchbot/webhook/secret-token')
			->assertSuccessful();
	}

	public function test_it_deletes_the_configured_webhook_url(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		$this->artisan('switchbot:webhook delete')->assertSuccessful();

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'deleteWebhook')
			&& $request['url'] === 'https://example.com/switchbot/webhook/secret-token');
	}

	public function test_it_warns_when_no_webhooks_are_registered(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['urls' => []],
		])]);

		$this->artisan('switchbot:webhook list')
			->expectsOutputToContain('No webhooks are registered')
			->assertSuccessful();
	}

	public function test_it_reports_an_unknown_action(): void
	{
		$this->artisan('switchbot:webhook frobnicate')
			->expectsOutputToContain('Unknown action')
			->assertFailed();

		Http::assertNothingSent();
	}

	public function test_it_fails_when_no_url_is_provided(): void
	{
		config(['switchbot.webhook_url' => null]);

		$this->artisan('switchbot:webhook register')->assertFailed();

		Http::assertNothingSent();
	}

	public function test_it_accepts_a_url_option_override(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		$this->artisan('switchbot:webhook register --url=https://tunnel.example.com/switchbot/webhook/secret-token')
			->assertSuccessful();

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'setupWebhook')
			&& $request['url'] === 'https://tunnel.example.com/switchbot/webhook/secret-token');
	}

	public function test_it_reports_api_errors_raised_during_a_command(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 190, 'message' => 'Device internal error'])]);

		$this->artisan('switchbot:webhook register')
			->expectsOutputToContain('Device internal error')
			->assertFailed();
	}

	public function test_it_fails_when_credentials_are_missing(): void
	{
		config(['switchbot.token' => null, 'switchbot.secret' => null]);

		$this->artisan('switchbot:webhook register')->assertFailed();
	}
}
