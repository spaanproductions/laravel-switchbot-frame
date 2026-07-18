<section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
	<div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
		<div>
			<h2 class="font-display text-xl font-semibold text-stone-900">Battery webhook</h2>
			<p class="mt-1 max-w-xl text-sm text-stone-500">
				The status API reports the battery stuck at 0%, so the real level comes from SwitchBot's
				change-report webhook. Register this app's public URL to start receiving it.
			</p>
		</div>
		<button type="button" wire:click="register" wire:loading.attr="disabled" wire:target="register"
			@disabled(! $configured || ! $hasToken)
			class="mt-3 shrink-0 rounded-lg bg-[var(--sb-accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[var(--sb-accent-hover)] disabled:cursor-not-allowed disabled:opacity-40 sm:mt-0">
			<span wire:loading.remove wire:target="register">Register webhook</span>
			<span wire:loading wire:target="register">Registering&hellip;</span>
		</button>
	</div>

	@if (! $configured)
		<p class="mt-4 text-sm text-stone-500">Add your SwitchBot credentials above to manage the webhook.</p>
	@elseif (! $hasToken)
		<p class="mt-4 text-sm text-red-600">
			Set <code class="rounded bg-stone-100 px-1.5 py-0.5 font-medium text-[var(--sb-accent)]">SWITCHBOT_WEBHOOK_TOKEN</code>
			in your <code class="rounded bg-stone-100 px-1.5 py-0.5 font-medium text-[var(--sb-accent)]">.env</code> (a long random string) so the receiver can verify incoming calls.
		</p>
	@else
		<div class="mt-4 space-y-4">
			<div>
				<p class="text-xs font-medium uppercase tracking-wider text-stone-400">Receiver URL</p>
				<p class="mt-1 break-all rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 font-mono text-xs text-stone-600">{{ $expectedUrl }}</p>
				<p class="mt-1 text-xs text-stone-400">Must be publicly reachable over HTTPS — SwitchBot cannot call a <code>.test</code> host.</p>
			</div>

			@if ($error)
				<p class="text-sm text-red-600">{{ $error }}</p>
			@endif

			<div>
				<p class="text-xs font-medium uppercase tracking-wider text-stone-400">Registered on your account</p>
				@if ($urls === [])
					<p class="mt-1 text-sm text-stone-500">No webhooks registered yet.</p>
				@else
					<ul class="mt-2 space-y-2">
						@foreach ($urls as $url)
							<li wire:key="webhook-{{ md5($url) }}" class="flex items-center justify-between gap-3 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2">
								<span class="min-w-0 break-all font-mono text-xs text-stone-600">{{ $url }}</span>
								<button type="button" wire:click="delete('{{ $url }}')" wire:confirm="Delete this webhook?"
									class="shrink-0 rounded-lg px-2 py-1 text-xs text-stone-400 transition hover:bg-red-50 hover:text-red-600">
									Delete
								</button>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
		</div>
	@endif

	@if ($loggingEnabled)
		<div class="mt-6 border-t border-stone-200 pt-4">
			<div class="flex items-center justify-between">
				<p class="text-xs font-medium uppercase tracking-wider text-stone-400">Recent webhook bodies <span class="text-stone-300">(debug)</span></p>
				@if ($recentEvents->isNotEmpty())
					<button type="button" wire:click="clearLog" wire:confirm="Clear the stored webhook log?"
						class="text-xs text-stone-400 transition hover:text-red-600">Clear</button>
				@endif
			</div>

			@if ($recentEvents->isEmpty())
				<p class="mt-2 text-sm text-stone-500">Nothing stored yet &mdash; SwitchBot only pushes on change.</p>
			@else
				<ul class="mt-2 space-y-2">
					@foreach ($recentEvents as $event)
						<li wire:key="event-{{ $event->id }}">
							<details class="group rounded-lg border border-stone-200 bg-stone-50">
								<summary class="cursor-pointer px-3 py-2 text-xs text-stone-500 marker:text-stone-400">
									{{ $event->created_at?->diffForHumans() }} <span class="text-stone-400">&middot; #{{ $event->id }}</span>
								</summary>
								<pre class="overflow-x-auto px-3 pb-3 text-[11px] leading-relaxed text-stone-600">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
							</details>
						</li>
					@endforeach
				</ul>
			@endif
		</div>
	@endif

	@if ($notice)
		<p class="mt-4 text-sm {{ $noticeType === 'error' ? 'text-red-600' : 'text-[var(--sb-accent)]' }}">{{ $notice }}</p>
	@endif
</section>
