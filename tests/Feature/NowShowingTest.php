<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\NowShowing;
use SpaanProductions\LaravelSwitchbotFrame\Repositories\FrameStatusStore;

class NowShowingTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		config([
			'switchbot.token' => 'test-token',
			'switchbot.secret' => 'test-secret',
			'switchbot.device_id' => 'FRAME123456',
		]);
	}

	public function test_it_shows_the_current_image_and_vitals(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/status' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => [
				'battery' => 87,
				'onlineStatus' => 'online',
				'version' => 'V1.0-1.2',
				'imageUrl' => 'https://frames.example.com/current.jpg',
			],
		])]);

		Livewire::test(NowShowing::class)
			->assertSee('87%')
			->assertSee('Online')
			->assertSee('https://frames.example.com/current.jpg', escape: false);
	}

	public function test_it_prefers_the_webhook_battery_over_the_buggy_api_value(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['battery' => 0, 'onlineStatus' => 'online', 'imageUrl' => 'https://frames.example.com/a.jpg'],
		])]);

		app(FrameStatusStore::class)->put(['battery' => 38, 'deviceMac' => 'B0E9FE9D608A']);

		Livewire::test(NowShowing::class)
			->assertSee('38%')
			->assertDontSee('0%');
	}

	public function test_it_sends_the_next_command(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(NowShowing::class)->call('next');

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'commands') && $request['command'] === 'next');
	}

	public function test_it_sends_the_previous_command(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response(['statusCode' => 100, 'message' => 'success', 'body' => []])]);

		Livewire::test(NowShowing::class)->call('previous');

		Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'commands') && $request['command'] === 'previous');
	}

	public function test_it_refetches_the_status_when_refreshed(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/status' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['battery' => 50, 'onlineStatus' => 'online'],
		])]);

		Livewire::test(NowShowing::class)->call('refreshStatus');

		// One status call on the initial render (which caches it), a second after
		// refreshStatus busts the 20s cache and forces the re-render to refetch.
		Http::assertSentCount(2);
	}

	public function test_it_refetches_the_status_when_a_frame_is_pushed(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/status' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['battery' => 50, 'onlineStatus' => 'online'],
		])]);

		Livewire::test(NowShowing::class)->dispatch('frame-updated');

		Http::assertSentCount(2);
	}
}
