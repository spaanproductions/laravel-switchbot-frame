<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the pre-compiled standalone stylesheet so the page needs no host
 * Tailwind build. Prefers the published copy in public/ when present (so a host
 * that published + customized the CSS is honoured), otherwise falls back to the
 * package's own dist file. An invokable controller (not a closure) keeps the
 * route compatible with `route:cache`.
 */
class StylesheetController
{
	public function __invoke(): Response
	{
		$published = public_path('vendor/switchbot/app.css');

		$path = is_file($published)
			? $published
			: __DIR__ . '/../../../dist/switchbot-frame.css';

		return response(
			is_file($path) ? (string) file_get_contents($path) : '',
			200,
			[
				'Content-Type' => 'text/css; charset=UTF-8',
				'Cache-Control' => 'public, max-age=31536000, immutable',
			],
		);
	}
}
