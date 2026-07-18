<div wire:poll.30s class="flex h-full flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
	<div class="flex items-center justify-between">
		<div>
			<h2 class="font-display text-xl font-semibold text-stone-900">Now showing</h2>
			<p class="text-[11px] text-stone-400">Last image reported by SwitchBot</p>
		</div>

		@if ($status)
			<div class="flex items-center gap-3 text-xs text-stone-500">
				@if (($status['onlineStatus'] ?? null) !== null)
					<span class="flex items-center gap-1.5">
						<span class="h-1.5 w-1.5 rounded-full {{ ($status['onlineStatus'] ?? '') === 'online' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
						{{ ucfirst($status['onlineStatus'] ?? 'unknown') }}
					</span>
				@endif
				@if (($status['battery'] ?? 0) > 0)
					<span class="flex items-center gap-1.5" title="Battery{{ isset($status['batterySource']) && $status['batteryUpdatedAt'] ? ' (via webhook, updated ' . $status['batteryUpdatedAt']->diffForHumans() . ')' : '' }}">
						<x-heroicon-o-battery-50 class="h-3.5 w-3.5" />
						{{ $status['battery'] }}%
					</span>
				@endif
				@if (isset($status['version']))
					<span class="hidden text-stone-400 sm:inline">FW {{ $status['version'] }}</span>
				@endif
			</div>
		@endif
	</div>

	<div class="relative mt-4 flex-1">
		{{-- SwitchBot AI Art Frame: portrait (standing) aluminium frame, centred in the available width --}}
		<div class="flex h-full items-center justify-center">
			<div class="relative mx-auto w-full max-w-md rounded-[7px] p-[5px] shadow-[0_18px_45px_-15px_rgba(45,34,22,0.4)]"
				style="background-image: linear-gradient(145deg, #ece7df, #d8cfc1, #c3b7a4);">
				<div class="rounded-[3px] bg-white p-5 shadow-[inset_0_0_0_1px_rgba(0,0,0,0.05)] sm:p-6">
					<div class="relative {{ $aspect->cssClass() }} overflow-hidden bg-stone-100 shadow-[inset_0_2px_10px_rgba(0,0,0,0.12)] ring-1 ring-black/5">
						@if ($status && filled($status['imageUrl'] ?? null))
							<img src="{{ $status['imageUrl'] }}" alt="Currently displayed on the frame" class="h-full w-full object-contain" />
						@elseif (! $configured)
							<div class="flex h-full items-center justify-center px-6 text-center text-sm text-stone-500">
								Add your SwitchBot credentials to see what's on the frame.
							</div>
						@elseif ($error)
							<div class="flex h-full items-center justify-center px-6 text-center text-sm text-red-600">
								{{ $error }}
							</div>
						@else
							<div class="flex h-full items-center justify-center text-sm text-stone-500">
								Nothing reported by the frame yet.
							</div>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="mt-5 flex items-center justify-between">
		<div class="flex items-center gap-2">
			<button type="button" wire:click="previous" wire:loading.attr="disabled" @disabled(! $configured)
				class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-700 transition hover:border-[var(--sb-accent)]/50 hover:text-[var(--sb-accent)] disabled:cursor-not-allowed disabled:opacity-40">
				&larr; Previous
			</button>
			<button type="button" wire:click="next" wire:loading.attr="disabled" @disabled(! $configured)
				class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-700 transition hover:border-[var(--sb-accent)]/50 hover:text-[var(--sb-accent)] disabled:cursor-not-allowed disabled:opacity-40">
				Next &rarr;
			</button>
		</div>

		<button type="button" wire:click="refreshStatus" title="Refresh status"
			class="flex items-center gap-1.5 text-xs text-stone-400 transition hover:text-[var(--sb-accent)]">
			<x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="h-3.5 w-3.5" />
			Refresh
		</button>
	</div>

	<p class="mt-3 text-xs leading-relaxed text-stone-400">
		The frame stores up to 10 images locally &mdash; manage its album in the SwitchBot app. E-ink refreshes take ~30 seconds.
		In slideshow mode (or after changes made in the SwitchBot app) the wall may show a different image than this: the API only
		exposes one image and it can lag the frame's actual album.
	</p>
</div>
