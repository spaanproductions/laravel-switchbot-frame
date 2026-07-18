<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotApi;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotException;

class SwitchBotApiTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		Cache::flush();

		config([
			'switchbot.token' => 'test-token',
			'switchbot.secret' => 'test-secret',
			'switchbot.device_id' => 'FRAME123456',
		]);
	}

	/** @return array<string, mixed> */
	private function success(array $body = []): array
	{
		return ['statusCode' => 100, 'message' => 'success', 'body' => $body];
	}

	public function test_it_signs_every_request_with_hmac_headers(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response($this->success(['deviceList' => []]))]);

		app(SwitchBotApi::class)->devices();

		Http::assertSent(function (Request $request): bool {
			$t = $request->header('t')[0];
			$nonce = $request->header('nonce')[0];

			$expected = strtoupper(base64_encode(
				hash_hmac('sha256', 'test-token' . $t . $nonce, 'test-secret', true),
			));

			return $request->hasHeader('Authorization', 'test-token')
				&& is_numeric($t)
				&& $request->header('sign')[0] === $expected;
		});
	}

	public function test_it_filters_art_frames_from_the_device_list(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices' => Http::response($this->success(['deviceList' => [
			['deviceId' => 'HUB001', 'deviceType' => 'Hub 2'],
			['deviceId' => 'FRAME123456', 'deviceType' => 'AI Art Frame'],
		]]))]);

		$frames = app(SwitchBotApi::class)->artFrames();

		$this->assertCount(1, $frames);
		$this->assertSame('FRAME123456', $frames[0]['deviceId']);
	}

	public function test_it_sends_next_and_previous_commands(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/commands' => Http::response($this->success())]);

		app(SwitchBotApi::class)->nextImage();
		app(SwitchBotApi::class)->previousImage();

		Http::assertSent(fn (Request $request): bool => $request['command'] === 'next' && $request['parameter'] === 'default');
		Http::assertSent(fn (Request $request): bool => $request['command'] === 'previous');
	}

	public function test_it_uploads_an_image_as_base64(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/commands' => Http::response($this->success())]);

		app(SwitchBotApi::class)->uploadImageFromBase64('data:image/jpeg;base64,Zm9v');

		Http::assertSent(fn (Request $request): bool => $request['command'] === 'uploadImage'
			&& $request['parameter'] === ['imageBase64' => 'data:image/jpeg;base64,Zm9v']);
	}

	public function test_it_registers_and_deletes_webhooks(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response($this->success())]);

		app(SwitchBotApi::class)->setupWebhook('https://example.com/hook/secret');
		app(SwitchBotApi::class)->deleteWebhook('https://example.com/hook/secret');

		Http::assertSent(fn (Request $request): bool => $request['action'] === 'setupWebhook'
			&& $request['url'] === 'https://example.com/hook/secret'
			&& $request['deviceList'] === 'ALL');
		Http::assertSent(fn (Request $request): bool => $request['action'] === 'deleteWebhook');
	}

	public function test_it_throws_on_a_non_success_status_code(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 190, 'message' => 'Device internal error', 'body' => [],
		])]);

		$this->expectException(SwitchBotException::class);
		$this->expectExceptionMessage('Device internal error');

		app(SwitchBotApi::class)->status();
	}

	public function test_it_throws_when_credentials_are_missing(): void
	{
		config(['switchbot.token' => null, 'switchbot.secret' => null]);

		$this->expectException(SwitchBotException::class);
		$this->expectExceptionMessage('credentials are missing');

		app(SwitchBotApi::class)->devices();
	}

	public function test_it_auto_discovers_the_art_frame_when_no_device_id_is_configured(): void
	{
		config(['switchbot.device_id' => null]);

		Http::fake([
			'api.switch-bot.com/v1.1/devices' => Http::response($this->success(['deviceList' => [
				['deviceId' => 'AUTO123', 'deviceType' => 'AI Art Frame'],
			]])),
			'api.switch-bot.com/v1.1/devices/AUTO123/status' => Http::response($this->success(['battery' => 100])),
		]);

		app(SwitchBotApi::class)->status();

		Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/devices/AUTO123/status'));
	}

	public function test_it_throws_when_no_art_frame_is_found(): void
	{
		config(['switchbot.device_id' => null]);

		Http::fake(['api.switch-bot.com/v1.1/devices' => Http::response($this->success(['deviceList' => [
			['deviceId' => 'HUB001', 'deviceType' => 'Hub 2'],
		]]))]);

		$this->expectException(SwitchBotException::class);
		$this->expectExceptionMessage('No AI Art Frame');

		app(SwitchBotApi::class)->status();
	}

	public function test_it_updates_a_webhook(): void
	{
		Http::fake(['api.switch-bot.com/*' => Http::response($this->success())]);

		app(SwitchBotApi::class)->updateWebhook('https://example.test/hook');

		Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/webhook/updateWebhook')
			&& $request['action'] === 'updateWebhook'
			&& $request['config']['url'] === 'https://example.test/hook'
			&& $request['config']['enable'] === true);
	}

	public function test_it_uploads_an_image_from_a_url(): void
	{
		Http::fake(['api.switch-bot.com/v1.1/devices/FRAME123456/commands' => Http::response($this->success())]);

		app(SwitchBotApi::class)->uploadImageFromUrl('https://example.test/pic.jpg');

		Http::assertSent(fn (Request $request): bool => $request['command'] === 'uploadImage'
			&& $request['parameter'] === ['imageUrl' => 'https://example.test/pic.jpg']);
	}

	public function test_it_retries_once_on_a_connection_error(): void
	{
		$calls = 0;

		Http::fake(['api.switch-bot.com/*' => function () use (&$calls): PromiseInterface {
			$calls++;

			if ($calls === 1) {
				throw new ConnectionException('boom');
			}

			return Http::response($this->success());
		}]);

		$result = app(SwitchBotApi::class)->status();

		$this->assertSame(2, $calls);
		$this->assertSame([], $result);
	}
}
