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
];
