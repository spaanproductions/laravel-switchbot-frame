@stack('scripts')

{{-- Prefer the published static scripts (served straight by the web server); fall back to the routes. --}}
@php
	$sbAppJs = is_file(public_path('vendor/switchbot/app.js'))
		? asset('vendor/switchbot/app.js')
		: route('switchbot.assets.js', ['asset' => 'app']);
	$sbHeicJs = is_file(public_path('vendor/switchbot/heic2any.min.js'))
		? asset('vendor/switchbot/heic2any.min.js')
		: route('switchbot.assets.js', ['asset' => 'heic2any']);
@endphp

{{-- Client-side HEIC → JPEG conversion. The interceptor is tiny and always loaded; the
	 heic2any decoder (~1.3 MB) is lazy-loaded from this URL only on the first HEIC pick. --}}
<script>window.SwitchbotFrame = Object.assign(window.SwitchbotFrame || {}, { heicConverterUrl: @js($sbHeicJs) });</script>
<script src="{{ $sbAppJs }}" defer></script>

{{--
	Extension point at the end of <body>. Publish the views and edit this file to
	add your own scripts. Livewire + Alpine are already injected above.
--}}
