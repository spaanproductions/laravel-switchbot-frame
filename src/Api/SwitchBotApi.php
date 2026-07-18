<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Api;

use Closure;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;

/**
 * Client for the SwitchBot Cloud API v1.1.
 *
 * The AI Art Frame supports three commands: next, previous and uploadImage
 * (by public URL or base64 data URI). Its status exposes the battery level and
 * the URL of the image currently displayed.
 *
 * @docs https://github.com/OpenWonderLabs/SwitchBotAPI
 */
class SwitchBotApi
{
	private const string ART_FRAME_DEVICE_TYPE = 'AI Art Frame';

	/** Get the base URI for the SwitchBot API. */
	public function baseUri(): string
	{
		return 'https://api.switch-bot.com/v1.1';
	}

	public function isConfigured(): bool
	{
		return filled(config('switchbot.token')) && filled(config('switchbot.secret'));
	}

	/**
	 * All physical devices linked to the SwitchBot account.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function devices(): array
	{
		return $this->get('/devices')['deviceList'] ?? [];
	}

	/**
	 * All AI Art Frames linked to the SwitchBot account.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function artFrames(): array
	{
		return array_values(array_filter(
			$this->devices(),
			fn (array $device): bool => ($device['deviceType'] ?? null) === self::ART_FRAME_DEVICE_TYPE,
		));
	}

	/** The device ID of the art frame, configured or auto-discovered (cached a day). */
	public function resolveArtFrameId(): string
	{
		if (filled(config('switchbot.device_id'))) {
			return config('switchbot.device_id');
		}

		return Cache::remember('switchbot.art-frame-id', now()->addDay(), function (): string {
			$frames = $this->artFrames();

			if ($frames === []) {
				throw new SwitchBotException('No AI Art Frame was found on this SwitchBot account.');
			}

			if (count($frames) > 1) {
				Log::warning('Multiple SwitchBot AI Art Frames found; using the first. Set SWITCHBOT_DEVICE_ID to be explicit.', ['count' => count($frames)]);
			}

			return $frames[0]['deviceId'];
		});
	}

	/**
	 * Current device status (battery, imageUrl, version, onlineStatus).
	 *
	 * @return array<string, mixed>
	 */
	public function status(?string $deviceId = null): array
	{
		return $this->get(sprintf('/devices/%s/status', $deviceId ?? $this->resolveArtFrameId()));
	}

	/** @return array<string, mixed> */
	public function nextImage(?string $deviceId = null): array
	{
		return $this->command($deviceId, 'next');
	}

	/** @return array<string, mixed> */
	public function previousImage(?string $deviceId = null): array
	{
		return $this->command($deviceId, 'previous');
	}

	/** @return array<string, mixed> */
	public function uploadImageFromUrl(string $imageUrl, ?string $deviceId = null): array
	{
		return $this->command($deviceId, 'uploadImage', ['imageUrl' => $imageUrl]);
	}

	/** @return array<string, mixed> */
	public function uploadImageFromBase64(string $dataUri, ?string $deviceId = null): array
	{
		return $this->command($deviceId, 'uploadImage', ['imageBase64' => $dataUri]);
	}

	/** @return array<string, mixed> */
	public function setupWebhook(string $url): array
	{
		return $this->post('/webhook/setupWebhook', [
			'action' => 'setupWebhook',
			'url' => $url,
			'deviceList' => 'ALL',
		]);
	}

	/** @return array<int, string> */
	public function webhookUrls(): array
	{
		return $this->post('/webhook/queryWebhook', ['action' => 'queryUrl'])['urls'] ?? [];
	}

	/** @return array<string, mixed> */
	public function updateWebhook(string $url, bool $enable = true): array
	{
		return $this->post('/webhook/updateWebhook', [
			'action' => 'updateWebhook',
			'config' => ['url' => $url, 'enable' => $enable],
		]);
	}

	/** @return array<string, mixed> */
	public function deleteWebhook(string $url): array
	{
		return $this->post('/webhook/deleteWebhook', [
			'action' => 'deleteWebhook',
			'url' => $url,
		]);
	}

	/**
	 * @param  string|array<string, string>  $parameter
	 * @return array<string, mixed>
	 */
	private function command(?string $deviceId, string $command, string|array $parameter = 'default'): array
	{
		return $this->post(sprintf('/devices/%s/commands', $deviceId ?? $this->resolveArtFrameId()), [
			'commandType' => 'command',
			'command' => $command,
			'parameter' => $parameter,
		]);
	}

	/** @return array<string, mixed> */
	private function get(string $uri): array
	{
		return $this->parse($this->send(fn (PendingRequest $client): Response => $client->timeout(30)->get($uri)));
	}

	/**
	 * @param  array<string, mixed>  $body
	 * @return array<string, mixed>
	 */
	private function post(string $uri, array $body): array
	{
		return $this->parse($this->send(fn (PendingRequest $client): Response => $client->timeout(60)->post($uri, $body)));
	}

	/**
	 * Send a signed request, retrying only on connection failures.
	 *
	 * `throw: false` keeps failed HTTP responses (e.g. 413, 401) flowing back to
	 * parse() so they surface as a SwitchBotException carrying the API's own
	 * message, instead of a raw RequestException the callers do not catch.
	 *
	 * @param  Closure(PendingRequest): Response  $request
	 */
	private function send(Closure $request): Response
	{
		$client = Http::baseUrl($this->baseUri())
			->withHeaders($this->signedHeaders())
			->retry(2, 200, when: fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);

		try {
			return $request($client);
		} catch (ConnectionException $e) {
			throw new SwitchBotException('Could not reach the SwitchBot API: ' . $e->getMessage());
		}
	}

	/**
	 * HMAC-SHA256 request signing as required by the v1.1 API.
	 *
	 * @return array<string, string>
	 */
	private function signedHeaders(): array
	{
		if ( ! $this->isConfigured()) {
			throw new SwitchBotException('SwitchBot credentials are missing. Set SWITCHBOT_TOKEN and SWITCHBOT_SECRET in your .env file.');
		}

		$token = (string) config('switchbot.token');
		$time = (string) now()->getTimestampMs();
		$nonce = (string) Str::uuid();

		$signature = strtoupper(base64_encode(
			hash_hmac('sha256', $token . $time . $nonce, (string) config('switchbot.secret'), true),
		));

		return [
			'Authorization' => $token,
			'sign' => $signature,
			'nonce' => $nonce,
			't' => $time,
			'Content-Type' => 'application/json',
		];
	}

	/** @return array<string, mixed> */
	private function parse(Response $response): array
	{
		if ($response->failed()) {
			throw new SwitchBotException(sprintf(
				'SwitchBot API request failed with HTTP %d: %s',
				$response->status(),
				Str::limit($response->body(), 200),
			));
		}

		$statusCode = $response->json('statusCode');

		if ($statusCode !== 100) {
			throw new SwitchBotException(sprintf(
				'SwitchBot API returned status %s: %s',
				$statusCode ?? 'unknown',
				$response->json('message', 'no message'),
			));
		}

		return $response->json('body') ?? [];
	}
}
