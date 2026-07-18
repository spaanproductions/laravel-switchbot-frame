<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameStatus;
use SpaanProductions\LaravelSwitchbotFrame\Repositories\FrameStatusStore;

class FrameStatusStoreTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_stores_and_returns_the_latest_status(): void
	{
		resolve(FrameStatusStore::class)->put([
			'battery' => 42,
			'displayMode' => 'fill',
			'deviceMac' => 'AABBCCDDEEFF',
		]);

		$latest = resolve(FrameStatusStore::class)->latest();

		$this->assertSame(42, $latest['battery']);
		$this->assertSame('AABBCCDDEEFF', $latest['device_mac']);
		$this->assertSame('fill', $latest['display_mode']);
		$this->assertNotNull($latest['received_at']);
	}

	public function test_it_upserts_by_device_mac(): void
	{
		$store = resolve(FrameStatusStore::class);

		$store->put(['battery' => 42, 'deviceMac' => 'AABBCCDDEEFF']);
		$store->put(['battery' => 17, 'deviceMac' => 'AABBCCDDEEFF']);

		$this->assertSame(1, FrameStatus::query()->count());
		$this->assertSame(17, $store->latest()['battery']);
	}

	public function test_it_returns_null_when_empty(): void
	{
		$this->assertNull(resolve(FrameStatusStore::class)->latest());
	}

	public function test_it_forgets_all_status(): void
	{
		$store = resolve(FrameStatusStore::class);

		$store->put(['battery' => 42, 'deviceMac' => 'AABBCCDDEEFF']);
		$store->forget();

		$this->assertNull($store->latest());
		$this->assertSame(0, FrameStatus::query()->count());
	}

	public function test_it_coerces_and_defaults_missing_keys(): void
	{
		$store = resolve(FrameStatusStore::class);

		$store->put(['battery' => '55']);

		$latest = $store->latest();

		$this->assertSame(55, $latest['battery']);
		$this->assertNull($latest['display_mode']);
		$this->assertNull($latest['device_mac']);
	}
}
