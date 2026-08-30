<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the pre-compiled standalone JavaScript so the page needs no host JS
 * build: the HEIC-conversion interceptor ("app") and the vendored heic2any
 * decoder ("heic2any"). Prefers the published copy in public/ when present,
 * otherwise falls back to the package's own dist file. An invokable controller
 * (not a closure) keeps the route compatible with `route:cache`. The {asset}
 * segment is whitelisted at the route, so it is safe to map directly.
 */
class ScriptController
{
	/** @var array<string, array{published: string, dist: string}> */
	private const ASSETS = [
		'app' => [
			'published' => 'vendor/switchbot/app.js',
			'dist' => 'switchbot-frame.js',
		],
		'heic2any' => [
			'published' => 'vendor/switchbot/heic2any.min.js',
			'dist' => 'heic2any.min.js',
		],
	];

	public function __invoke(string $asset): Response
	{
		$map = self::ASSETS[$asset];

		$published = public_path($map['published']);

		$path = is_file($published)
			? $published
			: __DIR__ . '/../../../dist/' . $map['dist'];

		return response(
			is_file($path) ? (string) file_get_contents($path) : '',
			200,
			[
				'Content-Type' => 'application/javascript; charset=UTF-8',
				'Cache-Control' => 'public, max-age=31536000, immutable',
			],
		);
	}
}
