<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\SwitchBotWebhookLog;
use SpaanProductions\LaravelSwitchbotFrame\Repositories\FrameStatusStore;

class WebhookReceiverTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		config(['switchbot.webhook_token' => 'secret-token']);
	}

	/** @return array<string, mixed> */
	private function payload(int $battery = 38): array
	{
		return [
			'eventType' => 'changeReport',
			'eventVersion' => '1',
			'context' => [
				'deviceType' => 'AI Art Frame',
				'deviceMac' => 'B0E9FE9D608A',
				'displayMode' => 1,
				'battery' => $battery,
				'timeOfSample' => 1781799581544,
			],
		];
	}

	public function test_it_stores_the_battery_from_a_change_report(): void
	{
		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $this->payload(38))
			->assertOk()
			->assertJson(['statusCode' => 100]);

		$latest = app(FrameStatusStore::class)->latest();

		$this->assertSame(38, $latest['battery']);
		$this->assertSame('B0E9FE9D608A', $latest['device_mac']);
	}

	public function test_it_rejects_a_wrong_token(): void
	{
		$this->postJson(route('switchbot.webhook', ['token' => 'wrong-token']), $this->payload())
			->assertNotFound();

		$this->assertNull(app(FrameStatusStore::class)->latest());
	}

	public function test_it_returns_not_found_without_a_configured_token(): void
	{
		config(['switchbot.webhook_token' => null]);

		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $this->payload())
			->assertNotFound();
	}

	public function test_it_ignores_events_from_other_devices(): void
	{
		$payload = $this->payload();
		$payload['context']['deviceType'] = 'Hub 2';

		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $payload)->assertOk();

		$this->assertNull(app(FrameStatusStore::class)->latest());
	}

	public function test_it_stores_the_full_body_when_logging_is_enabled(): void
	{
		config(['switchbot.log_webhooks' => true]);

		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $this->payload(55))->assertOk();

		$this->assertSame(1, SwitchBotWebhookLog::query()->count());
		$this->assertSame(55, SwitchBotWebhookLog::query()->sole()->payload['context']['battery']);
	}

	public function test_it_does_not_store_the_body_when_logging_is_disabled(): void
	{
		config(['switchbot.log_webhooks' => false]);

		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $this->payload())->assertOk();

		$this->assertSame(0, SwitchBotWebhookLog::query()->count());
	}

	public function test_it_prunes_the_log_to_the_most_recent_rows(): void
	{
		config(['switchbot.log_webhooks' => true]);

		SwitchBotWebhookLog::factory()->count(501)->create();

		$this->postJson(route('switchbot.webhook', ['token' => 'secret-token']), $this->payload())->assertOk();

		$this->assertSame(500, SwitchBotWebhookLog::query()->count());
	}
}
