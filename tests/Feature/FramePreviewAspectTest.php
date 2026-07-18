<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\Gallery;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\NowShowing;

class FramePreviewAspectTest extends TestCase
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

		Http::fake(['api.switch-bot.com/*' => Http::response([
			'statusCode' => 100,
			'message' => 'success',
			'body' => ['battery' => 0, 'onlineStatus' => 'online', 'imageUrl' => 'https://frames.example.com/a.jpg'],
		])]);
	}

	public function test_the_now_showing_frame_defaults_to_a_portrait_aspect(): void
	{
		Livewire::test(NowShowing::class)->assertSee('aspect-[3/4]');
	}

	public function test_the_now_showing_frame_honours_the_configured_aspect(): void
	{
		config(['switchbot.aspect.now_showing' => 'landscape']);
		Livewire::test(NowShowing::class)
			->assertSee('aspect-[4/3]')
			->assertDontSee('aspect-[3/4]');

		config(['switchbot.aspect.now_showing' => 'square']);
		Livewire::test(NowShowing::class)->assertSee('aspect-square');
	}

	public function test_the_upload_dropzone_honours_the_configured_aspect(): void
	{
		// Force the nested "Now showing" frame to a different ratio so the assertion
		// can only match the dropzone's class.
		config([
			'switchbot.aspect.now_showing' => 'portrait',
			'switchbot.aspect.dropzone' => 'landscape',
		]);
		Livewire::test(Gallery::class)->assertSee('aspect-[4/3]');

		config([
			'switchbot.aspect.now_showing' => 'landscape',
			'switchbot.aspect.dropzone' => 'square',
		]);
		Livewire::test(Gallery::class)->assertSee('aspect-square');
	}
}
