<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotApi;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameAspect;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotException;
use SpaanProductions\LaravelSwitchbotFrame\Repositories\FrameStatusStore;

class NowShowing extends Component
{
	private const string STATUS_CACHE_KEY = 'switchbot.frame-status';

	public ?string $error = null;

	public function next(SwitchBotApi $api): void
	{
		$this->sendCommand(fn () => $api->nextImage());
	}

	public function previous(SwitchBotApi $api): void
	{
		$this->sendCommand(fn () => $api->previousImage());
	}

	public function refreshStatus(): void
	{
		Cache::forget(self::STATUS_CACHE_KEY);
	}

	#[On('frame-updated')]
	public function onFrameUpdated(): void
	{
		Cache::forget(self::STATUS_CACHE_KEY);
	}

	public function render(SwitchBotApi $api, FrameStatusStore $store): View
	{
		$status = $this->fetchStatus($api);
		$webhook = $store->latest();

		// The status API battery is stuck at 0 on current firmware; prefer the
		// real level pushed by the webhook when we have it.
		if ($status !== null && ($webhook['battery'] ?? 0) > 0) {
			$status['battery'] = $webhook['battery'];
			$status['batterySource'] = 'webhook';
			$status['batteryUpdatedAt'] = $webhook['received_at'] ?? null;
		}

		return ViewFactory::make('switchbot::livewire.now-showing', [
			'status' => $status,
			'configured' => $api->isConfigured(),
			'aspect' => FrameAspect::fromValue(config('switchbot.aspect.now_showing')),
		]);
	}

	/** @return array<string, mixed>|null */
	private function fetchStatus(SwitchBotApi $api): ?array
	{
		if ( ! $api->isConfigured()) {
			return null;
		}

		try {
			$status = Cache::remember(
				self::STATUS_CACHE_KEY,
				now()->addSeconds(20),
				fn (): array => $api->status(),
			);

			$this->error = null;

			return $status;
		} catch (SwitchBotException $e) {
			$this->error = $e->getMessage();

			return null;
		}
	}

	private function sendCommand(callable $command): void
	{
		try {
			$command();

			$this->error = null;

			Cache::forget(self::STATUS_CACHE_KEY);
		} catch (SwitchBotException $e) {
			$this->error = $e->getMessage();
		}
	}
}
