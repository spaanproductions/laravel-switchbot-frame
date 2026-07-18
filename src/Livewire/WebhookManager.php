<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotApi;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotException;
use SpaanProductions\LaravelSwitchbotFrame\Models\SwitchBotWebhookLog;

class WebhookManager extends Component
{
	/** @var array<int, string> */
	public array $urls = [];

	public ?string $error = null;

	public ?string $notice = null;

	public string $noticeType = 'success';

	public function mount(SwitchBotApi $api): void
	{
		$this->loadUrls($api);
	}

	public function register(SwitchBotApi $api): void
	{
		$token = (string) config('switchbot.webhook_token');
		$url = $this->expectedUrl();

		if ($token === '' || $url === null) {
			$this->flash('Set SWITCHBOT_WEBHOOK_TOKEN in your .env first.', 'error');

			return;
		}

		if ( ! Str::endsWith($url, $token)) {
			$this->flash('The webhook URL must end with your webhook token so the receiver can verify it.', 'error');

			return;
		}

		try {
			$api->setupWebhook($url);
		} catch (SwitchBotException $e) {
			$this->flash($e->getMessage(), 'error');

			return;
		}

		$this->loadUrls($api);
		$this->flash('Webhook registered. SwitchBot only pushes on change — press Next/Previous on the frame to get the first reading.');
	}

	public function delete(string $url, SwitchBotApi $api): void
	{
		try {
			$api->deleteWebhook($url);
		} catch (SwitchBotException $e) {
			$this->flash($e->getMessage(), 'error');

			return;
		}

		$this->loadUrls($api);
		$this->flash('Webhook deleted.');
	}

	public function clearLog(): void
	{
		SwitchBotWebhookLog::query()->delete();

		$this->flash('Webhook log cleared.');
	}

	/** The public URL SwitchBot should call, derived from the named route (or overridden in config). */
	public function expectedUrl(): ?string
	{
		$token = (string) config('switchbot.webhook_token');

		if ($token === '') {
			return null;
		}

		return config('switchbot.webhook_url') ?: route('switchbot.webhook', ['token' => $token]);
	}

	public function render(SwitchBotApi $api): View
	{
		$loggingEnabled = (bool) config('switchbot.log_webhooks');

		return ViewFactory::make('switchbot::livewire.webhook-manager', [
			'configured' => $api->isConfigured(),
			'hasToken' => filled(config('switchbot.webhook_token')),
			'expectedUrl' => $this->expectedUrl(),
			'loggingEnabled' => $loggingEnabled,
			'recentEvents' => $loggingEnabled
				? SwitchBotWebhookLog::query()->latest('id')->limit(10)->get()
				: collect(),
		]);
	}

	private function loadUrls(SwitchBotApi $api): void
	{
		if ( ! $api->isConfigured()) {
			$this->urls = [];

			return;
		}

		try {
			$this->urls = $api->webhookUrls();
			$this->error = null;
		} catch (SwitchBotException $e) {
			$this->error = $e->getMessage();
		}
	}

	private function flash(string $message, string $type = 'success'): void
	{
		$this->notice = $message;
		$this->noticeType = $type;
	}
}
