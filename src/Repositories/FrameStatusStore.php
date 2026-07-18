<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Repositories;

use SpaanProductions\LaravelSwitchbotFrame\Models\FrameStatus;

/**
 * Persists the most recent state pushed by the SwitchBot webhook.
 *
 * The public status API returns a broken battery value (stuck at 0) on current
 * firmware, but the device's own change-report webhook carries the real level.
 */
class FrameStatusStore
{
	/**
	 * Store the "context" object from an AI Art Frame change-report event.
	 *
	 * @param  array<string, mixed>  $context
	 */
	public function put(array $context): void
	{
		FrameStatus::updateOrCreate(
			['device_mac' => $context['deviceMac'] ?? null],
			[
				'battery' => isset($context['battery']) ? (int) $context['battery'] : null,
				'display_mode' => $context['displayMode'] ?? null,
				'received_at' => now(),
			],
		);
	}

	/**
	 * The most recent webhook payload, or null if none received yet.
	 *
	 * @return array<string, mixed>|null
	 */
	public function latest(): ?array
	{
		$row = FrameStatus::query()->latest('received_at')->first();

		if ($row === null) {
			return null;
		}

		return [
			'battery' => $row->battery,
			'display_mode' => $row->display_mode,
			'device_mac' => $row->device_mac,
			'received_at' => $row->received_at,
		];
	}

	public function forget(): void
	{
		FrameStatus::query()->delete();
	}
}
