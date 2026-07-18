<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use SpaanProductions\LaravelSwitchbotFrame\Models\SwitchBotWebhookLog;
use SpaanProductions\LaravelSwitchbotFrame\Repositories\FrameStatusStore;

class WebhookController
{
	/**
	 * Receive change-report events pushed by the SwitchBot cloud.
	 *
	 * SwitchBot does not sign outgoing webhooks, so the endpoint is protected by
	 * an unguessable token in the URL path that must match our config.
	 */
	public function __invoke(Request $request, string $token, FrameStatusStore $store): JsonResponse
	{
		$expected = (string) config('switchbot.webhook_token');

		abort_if($expected === '' || ! hash_equals($expected, $token), 404);

		if (config('switchbot.log_webhooks')) {
			SwitchBotWebhookLog::record($request->all());
		}

		$context = $request->input('context', []);

		if (($context['deviceType'] ?? null) === 'AI Art Frame') {
			$store->put($context);
		}

		return response()->json(['statusCode' => 100]);
	}
}
