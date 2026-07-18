<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="csrf-token" content="{{ csrf_token() }}">

		@include('switchbot::partials.head')
		@include('switchbot::partials.styles')

		@livewireStyles
	</head>
	<body class="min-h-screen bg-[#faf8f5] text-[#2b2622] antialiased [background-image:radial-gradient(ellipse_at_top,rgba(194,104,63,0.06),transparent_55%)]">
		@include('switchbot::partials.header')

		{{ $slot }}

		@stack('modals')

		@livewireScripts

		@include('switchbot::partials.scripts')
	</body>
</html>
