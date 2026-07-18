@php($hasProcessing = $images->contains(fn ($image) => $image->isProcessing()))
<div class="mx-auto max-w-7xl px-4 pb-24 pt-4 sm:px-6 lg:px-8" @if ($hasProcessing) wire:poll.3s @endif>

		{{-- Header --}}
		<header class="flex flex-col gap-2 pb-8 sm:flex-row sm:items-end sm:justify-between">
			<div>
				<p class="text-xs font-medium uppercase tracking-[0.25em] text-[var(--sb-accent)]">Woonkamer &middot; AI Art Frame 8A</p>
				<h1 class="mt-2 font-display text-4xl font-semibold tracking-tight text-stone-900">The Gallery</h1>
			</div>
			<p class="max-w-sm text-sm leading-relaxed text-stone-500">
				Your collection, prepared for e-ink and sent to the frame on the wall.
			</p>
		</header>

		@unless ($configured)
			{{-- Setup card --}}
			<div class="mb-10 rounded-2xl border border-[var(--sb-accent)]/20 bg-[var(--sb-accent)]/5 p-6">
				<h2 class="font-display text-lg font-semibold text-stone-900">Connect your SwitchBot account</h2>
				<p class="mt-2 text-sm text-stone-600">
					The token &amp; secret belong to your <span class="font-medium text-stone-900">account</span>, not the frame &mdash;
					look under the <span class="font-medium text-stone-900">Profile</span> tab, <span class="italic">not</span> the device's own settings.
				</p>
				<ol class="mt-3 list-inside list-decimal space-y-1 text-sm text-stone-600">
					<li>In the SwitchBot app, open the <span class="font-medium text-stone-900">Profile</span> tab &rarr; <span class="font-medium text-stone-900">Preferences</span> <span class="text-stone-400">(NL: Profiel &rarr; Voorkeuren)</span></li>
					<li>Tap <span class="font-medium text-stone-900">App Version</span> about 10 times to unlock <span class="font-medium text-stone-900">Developer Options</span></li>
					<li>Copy the token and secret into <code class="rounded bg-stone-100 px-1.5 py-0.5 font-medium text-[var(--sb-accent)]">SWITCHBOT_TOKEN</code> and <code class="rounded bg-stone-100 px-1.5 py-0.5 font-medium text-[var(--sb-accent)]">SWITCHBOT_SECRET</code></li>
				</ol>
			</div>
		@endunless

		<div class="grid gap-8 lg:grid-cols-3">

			{{-- Now showing --}}
			<section class="lg:col-span-2">
				<livewire:switchbot.now-showing />
			</section>

			{{-- Upload --}}
			<section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
				<div class="flex items-start justify-between gap-3">
					<h2 class="font-display text-xl font-semibold text-stone-900">Add to library</h2>
					@if (\SpaanProductions\LaravelSwitchbotFrame\Support\ImageStudio::enabled())
						<a href="{{ route('switchbot.studio') }}"
							class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[var(--sb-accent)] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[var(--sb-accent-hover)]">
							<x-heroicon-o-sparkles class="h-4 w-4" />
							Create with AI
						</a>
					@endif
				</div>
				<p class="mt-1 text-sm text-stone-500">JPEG, PNG, WebP &mdash; tuned for the e-ink panel.</p>

				<form wire:submit="save" class="mt-5 space-y-5">
					<label class="group relative flex {{ $dropzoneAspect->cssClass() }} cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border border-dashed border-stone-300 bg-stone-50 transition hover:border-[var(--sb-accent)]/60 hover:bg-[var(--sb-accent)]/[0.03]">
						@if ($photo && $photo->isPreviewable())
							<img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="absolute inset-0 h-full w-full object-cover" />
							<span class="absolute bottom-2 right-2 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-stone-600 shadow-sm">Choose another</span>
						@else
							<x-heroicon-o-photo class="h-8 w-8 text-stone-300 transition group-hover:text-[var(--sb-accent)]" />
							<span class="mt-2 text-sm text-stone-500">Drop a photo or click to browse</span>
							<span class="mt-1 text-xs text-stone-400">{{ $dropzoneAspect->label() }}, to match the frame</span>
						@endif
						<input type="file" wire:model="photo" accept="image/*" class="absolute inset-0 cursor-pointer opacity-0" />
					</label>

					<div wire:loading wire:target="photo" class="text-xs text-[var(--sb-accent)]">Uploading&hellip;</div>
					@error('photo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

					<div>
						<label for="title" class="block text-xs font-medium uppercase tracking-wider text-stone-400">Title (optional)</label>
						<input id="title" type="text" wire:model="title" placeholder="Sunset over the IJ"
							class="mt-1.5 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 placeholder-stone-400 focus:border-[var(--sb-accent)] focus:outline-none focus:ring-1 focus:ring-[var(--sb-accent)]/40" />
						@error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
					</div>

					<label class="flex items-start gap-3">
						<input type="checkbox" wire:model="enhance" class="mt-0.5 h-4 w-4 rounded border-stone-300 text-[var(--sb-accent)] focus:ring-[var(--sb-accent)]/40" />
						<span>
							<span class="block text-sm text-stone-800">Optimize for e-ink</span>
							<span class="block text-xs text-stone-500">Boosts saturation &amp; contrast so it doesn't render dull on the Spectra&nbsp;6 panel</span>
						</span>
					</label>

					<button type="submit" wire:loading.attr="disabled" wire:target="save, photo"
						class="w-full rounded-lg bg-[var(--sb-accent)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[var(--sb-accent-hover)] disabled:cursor-not-allowed disabled:opacity-50">
						<span wire:loading.remove wire:target="save">Add to library</span>
						<span wire:loading wire:target="save">Optimizing&hellip;</span>
					</button>
				</form>
			</section>
		</div>

		{{-- Library --}}
		<section class="mt-14">
			<div class="flex items-baseline justify-between">
				<h2 class="font-display text-2xl font-semibold text-stone-900">Library</h2>
				<p class="text-sm text-stone-400">{{ $images->count() }} {{ Str::plural('image', $images->count()) }}</p>
			</div>

			@if ($images->isEmpty())
				<div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center">
					<p class="font-display text-lg text-stone-500">An empty gallery wall.</p>
					<p class="mt-1 text-sm text-stone-400">Add your first image above &mdash; everything you upload stays here, ready to re-hang anytime.</p>
				</div>
			@else
				<div class="mt-6 grid grid-cols-1 items-start gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
					@foreach ($images as $image)
						<article wire:key="image-{{ $image->id }}" class="group overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
							<div class="relative overflow-hidden bg-stone-100 {{ $image->isReady() && $image->width && $image->height ? '' : 'aspect-[3/4]' }}"
								@if ($image->isReady() && $image->width && $image->height) style="aspect-ratio: {{ $image->width }} / {{ $image->height }}" @endif>
								@if ($image->isReady())
									<img src="{{ $image->url }}" alt="{{ $image->title ?? $image->original_name }}" loading="lazy"
										class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" />
									@if ($image->optimized)
										<span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--sb-accent)] shadow-sm">E-ink tuned</span>
									@endif
								@elseif ($image->isProcessing())
									<div class="flex h-full w-full flex-col items-center justify-center gap-2 text-stone-400">
										<svg class="h-6 w-6 animate-spin text-[var(--sb-accent)]" viewBox="0 0 24 24" fill="none">
											<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
											<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
										</svg>
										<span class="text-xs">Optimizing for the panel&hellip;</span>
									</div>
								@else
									<div class="flex h-full w-full flex-col items-center justify-center gap-1 px-4 text-center text-red-600">
										<x-heroicon-o-exclamation-triangle class="h-6 w-6" />
										<span class="text-xs font-medium">Couldn't be processed</span>
										@if ($image->error)
											<span class="line-clamp-2 text-[11px] text-stone-500">{{ $image->error }}</span>
										@endif
									</div>
								@endif
							</div>
							<div class="flex items-start justify-between gap-3 p-4">
								<div class="min-w-0">
									<h3 class="truncate text-sm font-medium text-stone-900">{{ $image->title ?? $image->original_name }}</h3>
									<p class="mt-0.5 text-xs text-stone-400">
										@if ($image->isReady())
											{{ $image->width }}&times;{{ $image->height }} &middot; {{ $image->readable_file_size }}
											@if ($image->last_pushed_at)
												&middot; on frame {{ $image->last_pushed_at->diffForHumans() }}
											@endif
										@else
											{{ $image->status->label() }}
										@endif
									</p>
								</div>
								<div class="flex shrink-0 items-center gap-1.5">
									<button type="button" wire:click="pushToFrame({{ $image->id }})" wire:loading.attr="disabled" wire:target="pushToFrame({{ $image->id }})"
										@disabled(! $configured || ! $image->isReady()) title="{{ $configured ? 'Send to frame' : 'Add SwitchBot credentials first' }}"
										class="rounded-lg bg-[var(--sb-accent)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--sb-accent)] transition hover:bg-[var(--sb-accent)]/20 disabled:cursor-not-allowed disabled:opacity-40">
										<span wire:loading.remove wire:target="pushToFrame({{ $image->id }})">Hang it</span>
										<span wire:loading wire:target="pushToFrame({{ $image->id }})">Sending&hellip;</span>
									</button>
									@if (\SpaanProductions\LaravelSwitchbotFrame\Support\ImageStudio::enabled() && $image->isReady())
										<a href="{{ route('switchbot.studio', ['from' => $image->id]) }}" title="Edit with AI"
											class="rounded-lg px-2 py-1.5 text-xs text-stone-400 transition hover:bg-[var(--sb-accent)]/10 hover:text-[var(--sb-accent)]">
											<x-heroicon-o-sparkles class="h-4 w-4" />
										</a>
									@endif
									<button type="button" wire:click="deleteImage({{ $image->id }})" wire:confirm="Remove this image from your library?" title="Delete"
										class="rounded-lg px-2 py-1.5 text-xs text-stone-400 transition hover:bg-red-50 hover:text-red-600">
										<x-heroicon-o-trash class="h-4 w-4" />
									</button>
								</div>
							</div>
						</article>
					@endforeach
				</div>
			@endif
		</section>

		{{-- Webhook --}}
		<div class="mt-14">
			<livewire:switchbot.webhook-manager />
		</div>

		{{-- Toast: errors stay until dismissed so the exact message can be read; success auto-hides. --}}
		@if ($notice)
			<div x-data="{ show: true, isError: @js($noticeType === 'error') }"
				x-init="if (! isError) setTimeout(() => show = false, 6000)"
				x-show="show" x-transition.opacity.duration.300ms
				x-on:notice-shown.window="show = true; isError = @js($noticeType === 'error'); if (! isError) setTimeout(() => show = false, 6000)"
				class="fixed bottom-6 right-6 z-50 flex max-w-md items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg {{ $noticeType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-[var(--sb-accent)]/30 bg-white text-stone-800' }}">
				<span class="min-w-0 break-words">{{ $notice }}</span>
				<button type="button" @click="show = false" title="Dismiss" class="-mr-1 shrink-0 text-lg leading-none opacity-50 transition hover:opacity-100">&times;</button>
			</div>
		@endif
</div>
