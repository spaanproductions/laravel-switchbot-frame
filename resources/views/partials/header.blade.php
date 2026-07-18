@php($switchbotBackLink = config('switchbot.back_link'))

@if (($switchbotBackLink['route'] ?? null) || ($switchbotBackLink['url'] ?? null))
	<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
		<a href="{{ ($switchbotBackLink['route'] ?? null) ? route($switchbotBackLink['route']) : $switchbotBackLink['url'] }}"
			class="inline-flex items-center gap-2 text-sm text-stone-500 transition hover:text-[var(--sb-accent)]">
			<x-heroicon-o-arrow-left class="h-4 w-4" />
			{{ $switchbotBackLink['label'] ?? 'Back' }}
		</a>
	</div>
@endif

{{--
	Extension point above the page. Publish the views and edit this file to add
	breadcrumbs, a nav bar, or anything specific to your application.
--}}
