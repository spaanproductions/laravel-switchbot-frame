{{--
	Styling for the standalone page. Publish the views to re-theme it — change the
	accent colour / fonts below, or swap the compiled stylesheet for your own.
--}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&family=inter:400,500,600,700&display=swap" rel="stylesheet">

{{-- Prefer the published static stylesheet (served straight by the web server); fall back to the route. --}}
@if (is_file(public_path('vendor/switchbot/app.css')))
	<link rel="stylesheet" href="{{ asset('vendor/switchbot/app.css') }}">
@else
	<link rel="stylesheet" href="{{ route('switchbot.assets.css') }}">
@endif

<style>
	[x-cloak] { display: none !important; }
	:root { --sb-accent: #c2683f; --sb-accent-hover: #a8542f; }
	body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
	.font-display { font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; }
</style>
