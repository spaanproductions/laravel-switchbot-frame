<?php

return [
	/*
	 * SwitchBot Open API credentials. The token and secret belong to your
	 * SwitchBot *account*, not the frame — in the app, open Profile → Preferences
	 * and tap "App Version" about 10× to reveal Developer Options. device_id
	 * targets a specific AI Art Frame; leave it empty to auto-discover the frame
	 * on the account (the first one is used if several are present).
	 */
	'token' => env('SWITCHBOT_TOKEN'),
	'secret' => env('SWITCHBOT_SECRET'),
	'device_id' => env('SWITCHBOT_DEVICE_ID'),

	/*
	 * Battery-reporting webhook. The status API reports the frame battery stuck
	 * at 0%, so the real level arrives via SwitchBot's change-report webhook.
	 * webhook_token is an unguessable string embedded in the receiver path and
	 * verified on every incoming call. webhook_url optionally overrides the public
	 * HTTPS URL registered with SwitchBot — leave it empty to use the app's own
	 * route. SwitchBot cannot reach a local ".test" host, so point this at a
	 * public tunnel or domain during local development.
	 */
	'webhook_url' => env('SWITCHBOT_WEBHOOK_URL'),
	'webhook_token' => env('SWITCHBOT_WEBHOOK_TOKEN'),

	/*
	 * Store the complete body of every accepted webhook call for inspection.
	 * A debugging aid — enable temporarily when something is not working as
	 * expected. The log self-prunes to the most recent rows.
	 */
	'log_webhooks' => env('SWITCHBOT_LOG_WEBHOOKS', false),

	/*
	 * Filesystem disk used to store uploaded originals and the optimized frame
	 * images. Must match a disk defined in config/filesystems.php.
	 */
	'disk' => env('SWITCHBOT_DISK', 's3'),

	/*
	 * Visibility applied when writing images to the disk. Leave empty to inherit
	 * the bucket default (recommended for S3 buckets with ACLs disabled — the
	 * streaming route + bucket "Block Public Access" are the authoritative
	 * controls). Set to "private" on ACL-enabled buckets for defence in depth.
	 */
	'disk_visibility' => env('SWITCHBOT_DISK_VISIBILITY'),

	/*
	 * Route registration. The admin page, image stream and CSS asset use
	 * `prefix` + `middleware` — it ships with `web` + `auth:sanctum`, so add any
	 * further authorization (roles, gates, a super-admin guard) here for your app.
	 * The public webhook receiver has its own prefix/middleware because SwitchBot
	 * calls it unauthenticated; it is verified by the unguessable token in the path.
	 */
	'routes' => [
		'prefix' => env('SWITCHBOT_ROUTE_PREFIX', 'switchbot'),
		'middleware' => ['web', 'auth:sanctum'],
		'webhook' => [
			'prefix' => env('SWITCHBOT_WEBHOOK_ROUTE_PREFIX', ''),
			'middleware' => ['api'],
		],
	],

	/*
	 * Back-link shown at the top of the standalone page — specific to the host
	 * app. Set `route` (a named route) or `url` to point it somewhere; leave both
	 * null to hide it entirely. `route` takes precedence when both are set.
	 */
	'back_link' => [
		'label' => env('SWITCHBOT_BACK_LINK_LABEL', 'Back'),
		'route' => null,
		'url' => env('SWITCHBOT_BACK_LINK_URL'),
	],

	/*
	 * On-screen aspect ratio for the frame previews in the admin UI. One of
	 * "portrait", "landscape" or "square", configured separately for the
	 * "Now showing" frame and the upload dropzone. Purely cosmetic — it does not
	 * change how images are cropped for the panel (that stays orientation-adaptive).
	 * The three possible Tailwind classes are safelisted in the compiled CSS.
	 */
	'aspect' => [
		'now_showing' => env('SWITCHBOT_NOW_SHOWING_ASPECT', 'portrait'),
		'dropzone' => env('SWITCHBOT_DROPZONE_ASPECT', 'portrait'),
	],

	/*
	 * AI Image Studio — a chat-style flow to generate and iteratively edit frame
	 * images from prompts. It requires the optional "laravel/ai" package
	 * (composer require laravel/ai) and only appears when that package is installed
	 * *and* `enabled` is true, so you can leave it off even when the package is
	 * present. Auth tokens come from laravel/ai's own env vars (OPENAI_API_KEY,
	 * GEMINI_API_KEY, …). Generated previews live on the same `disk` above, under
	 * `switchbot/ai-conversations/`.
	 */
	'ai' => [
		'enabled' => env('SWITCHBOT_AI_ENABLED', true),

		// Image generation. Provider/model are optional overrides — leave empty to use
		// config/ai.php's defaults (laravel/ai ships with "gemini" as its default image
		// provider, so either set GEMINI_API_KEY or pin SWITCHBOT_AI_IMAGE_PROVIDER=openai).
		// `default_aspect` is the shape for new conversations; empty follows the
		// `aspect.now_showing` orientation below.
		'images' => [
			'provider' => env('SWITCHBOT_AI_IMAGE_PROVIDER'),
			'model' => env('SWITCHBOT_AI_IMAGE_MODEL'),
			'default_aspect' => env('SWITCHBOT_AI_IMAGE_ASPECT'),
		],

		// "Improve prompt" is a *text* task, so it uses laravel/ai's default text
		// provider (config/ai.php `default`) unless you override it here.
		'improve' => [
			'provider' => env('SWITCHBOT_AI_IMPROVE_PROVIDER'),
			'model' => env('SWITCHBOT_AI_IMPROVE_MODEL'),
		],

		/*
		 * EXPERIMENTAL cost estimation. When enabled, a queued job estimates the
		 * USD cost of each generation and prompt improvement using token counts and
		 * public per-token prices from LiteLLM's model_prices_and_context_window.json
		 * (fetched and cached; re-fetched every `refresh_interval` hours). This is a
		 * rough INDICATION only — prices, image-token accounting and provider billing
		 * vary, so it MUST NOT be relied on for correctness, invoicing or budgeting.
		 */
		'cost_estimation' => [
			'enabled' => env('SWITCHBOT_AI_COST_ESTIMATION', false),
			'refresh_interval' => env('SWITCHBOT_AI_COST_REFRESH_HOURS', 24),
			'prices_url' => env('SWITCHBOT_AI_PRICES_URL', 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json'),
		],
	],

	/*
	 * E-ink optimizer. The Spectra 6 panel is muted, so uploads and images saved
	 * from the AI Studio are prepared with one of the presets below. `default` is
	 * the preset used for regular gallery uploads (the Studio lets you pick one per
	 * image). Any keys a preset omits fall back to sensible built-in values.
	 */
	'optimizer' => [
		'default' => env('SWITCHBOT_OPTIMIZER_PRESET', 'vivid'),

		/*
		 * Presets shown in the AI Studio's "Save to library" dialog, so the
		 * optimization can be matched to the image (vivid photos vs. light line art).
		 * Add your own and they appear automatically. The key is the identifier;
		 * `label` and `description` are shown to the user; the remaining keys are the
		 * boosts (`contrast` uses GD's IMG_FILTER_CONTRAST scale — negative = punchier).
		 */
		'presets' => [
			'vivid' => [
				'label' => 'Vivid',
				'description' => 'Boldest colours — best for photos that render dull on the panel.',
				'saturation' => 1.6,
				'contrast' => -18,
				'brightness' => 12,
				'sharpen' => true,
			],
			'balanced' => [
				'label' => 'Balanced',
				'description' => 'A moderate boost that keeps tones close to the original.',
				'saturation' => 1.35,
				'contrast' => 0,
				'brightness' => 0,
				'sharpen' => true,
			],
			'soft' => [
				'label' => 'Soft',
				'description' => 'Gentle and slightly darker — best for light artwork and line drawings.',
				'saturation' => 1.2,
				'contrast' => 8,
				'brightness' => -8,
				'sharpen' => false,
			],
			'grayscale' => [
				'label' => 'Grayscale',
				'description' => 'Timeless black & white — desaturates fully, with a touch of contrast.',
				'saturation' => 0,
				'contrast' => -8,
				'brightness' => 0,
				'sharpen' => true,
			],
		],
	],
];
