<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotApi;
use SpaanProductions\LaravelSwitchbotFrame\Api\SwitchBotException;

class ManageWebhook extends Command
{
	protected $signature = 'switchbot:webhook
		{action=register : register, list, or delete}
		{--url= : Public HTTPS URL (defaults to config switchbot.webhook_url)}';

	protected $description = 'Manage the SwitchBot webhook that pushes frame status (including the real battery level).';

	public function handle(SwitchBotApi $api): int
	{
		if ( ! $api->isConfigured()) {
			$this->error('SwitchBot credentials are missing. Set SWITCHBOT_TOKEN and SWITCHBOT_SECRET first.');

			return self::FAILURE;
		}

		try {
			return match ($this->argument('action')) {
				'register' => $this->register($api),
				'list' => $this->list($api),
				'delete' => $this->delete($api),
				default => $this->invalidAction(),
			};
		} catch (SwitchBotException $e) {
			$this->error($e->getMessage());

			return self::FAILURE;
		}
	}

	private function register(SwitchBotApi $api): int
	{
		$url = $this->resolveUrl();

		if ($url === null) {
			return self::FAILURE;
		}

		$token = (string) config('switchbot.webhook_token');

		if ($token === '' || ! Str::endsWith($url, $token)) {
			$this->error('The webhook URL must end with your SWITCHBOT_WEBHOOK_TOKEN so the receiver can verify it.');

			return self::FAILURE;
		}

		$api->setupWebhook($url);

		$this->info("Webhook registered: {$url}");
		$this->line('SwitchBot only pushes events on change — trigger Next/Previous on the frame to receive the first battery reading.');

		return self::SUCCESS;
	}

	private function list(SwitchBotApi $api): int
	{
		$urls = $api->webhookUrls();

		if ($urls === []) {
			$this->warn('No webhooks are registered on this account.');

			return self::SUCCESS;
		}

		$this->info('Registered webhook URLs:');

		foreach ($urls as $url) {
			$this->line("  • {$url}");
		}

		return self::SUCCESS;
	}

	private function delete(SwitchBotApi $api): int
	{
		$url = $this->resolveUrl();

		if ($url === null) {
			return self::FAILURE;
		}

		$api->deleteWebhook($url);

		$this->info("Webhook deleted: {$url}");

		return self::SUCCESS;
	}

	private function resolveUrl(): ?string
	{
		$url = $this->option('url') ?: config('switchbot.webhook_url');

		if (blank($url)) {
			$this->error('No URL provided. Pass --url= or set SWITCHBOT_WEBHOOK_URL in your .env.');

			return null;
		}

		return $url;
	}

	private function invalidAction(): int
	{
		$this->error("Unknown action [{$this->argument('action')}]. Use register, list, or delete.");

		return self::FAILURE;
	}
}
