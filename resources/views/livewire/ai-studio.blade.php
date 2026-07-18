<div class="mx-auto max-w-7xl px-4 pb-24 pt-4 sm:px-6 lg:px-8" @if ($generating) wire:poll.3s @endif>

	{{-- Header --}}
	<header class="flex flex-col gap-2 pb-8 sm:flex-row sm:items-end sm:justify-between">
		<div>
			<p class="text-xs font-medium uppercase tracking-[0.25em] text-[var(--sb-accent)]">AI Studio &middot; Generate &amp; edit</p>
			<h1 class="mt-2 font-display text-4xl font-semibold tracking-tight text-stone-900">Dream up a frame</h1>
		</div>
		<a href="{{ route('switchbot.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-500 transition hover:text-[var(--sb-accent)]">
			<x-heroicon-o-arrow-left class="h-4 w-4" />
			Back to the gallery
		</a>
	</header>

	<div class="grid gap-8 lg:grid-cols-[18rem_1fr]">

		{{-- History --}}
		<aside class="lg:sticky lg:top-4 lg:self-start">
			<button type="button" wire:click="newConversation"
				class="flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--sb-accent)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[var(--sb-accent-hover)]">
				<x-heroicon-o-plus class="h-4 w-4" />
				New conversation
			</button>

			<h2 class="mt-6 px-1 text-xs font-medium uppercase tracking-wider text-stone-400">History</h2>

			@if ($history->isEmpty())
				<p class="mt-3 px-1 text-sm text-stone-400">Your past conversations will appear here.</p>
			@else
				<ul class="mt-3 space-y-1">
					@foreach ($history as $item)
						<li wire:key="conversation-{{ $item->id }}"
							class="group flex items-center gap-1 rounded-lg px-1 {{ $conversation && $conversation->id === $item->id ? 'bg-[var(--sb-accent)]/10' : 'hover:bg-stone-100' }}">
							<button type="button" wire:click="loadConversation({{ $item->id }})"
								class="min-w-0 flex-1 px-2 py-2 text-left">
								<span class="block truncate text-sm font-medium {{ $conversation && $conversation->id === $item->id ? 'text-[var(--sb-accent)]' : 'text-stone-700' }}">{{ $item->title }}</span>
								<span class="block text-xs text-stone-400">{{ $item->updated_at?->diffForHumans() }}</span>
							</button>
							<button type="button" wire:click="deleteConversation({{ $item->id }})" wire:confirm="Delete this conversation and its generated images?"
								title="Delete conversation"
								class="shrink-0 rounded-md p-1.5 text-stone-300 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100">
								<x-heroicon-o-trash class="h-4 w-4" />
							</button>
						</li>
					@endforeach
				</ul>
			@endif
		</aside>

		{{-- Conversation --}}
		<section class="min-w-0">

			@if ($conversation && $conversation->estimatedCost() !== null)
				<p class="mb-4 text-xs text-stone-400">Estimated cost <span class="font-medium text-stone-500">~${{ number_format($conversation->estimatedCost(), 4) }}</span> &middot; experimental, indicative only</p>
			@endif

			{{-- Transcript --}}
			@if ($messages->isNotEmpty())
				<div class="space-y-6">
					@foreach ($messages as $message)
						<div wire:key="message-{{ $message->id }}">
							@if ($message->isUser())
								<div class="flex justify-end">
									<div class="max-w-lg rounded-2xl rounded-br-sm bg-[var(--sb-accent)] px-4 py-2.5 text-sm text-white shadow-sm">
										{{ $message->prompt }}
										@if ($message->image_path)
											<img src="{{ $message->url }}" alt="Starting image" class="mt-2 max-h-40 rounded-lg object-cover" />
										@endif
									</div>
								</div>
							@else
								<div class="flex justify-start">
									<div class="w-full max-w-xl overflow-hidden rounded-2xl rounded-bl-sm border border-stone-200 bg-white shadow-sm">
										@if ($message->isReady() && $message->image_path)
											<div class="relative bg-stone-100"
												@if ($message->width && $message->height) style="aspect-ratio: {{ $message->width }} / {{ $message->height }}" @endif>
												<img src="{{ $message->url }}" alt="Generated image" class="h-full w-full object-contain" />
											</div>
											<div class="flex items-center justify-between gap-3 p-3">
												<span class="text-xs text-stone-400">
													@if ($message->width && $message->height){{ $message->width }}&times;{{ $message->height }} &middot; @endif{{ $message->readable_file_size }}@if ($message->totalTokens() !== null) &middot; {{ number_format((int) $message->input_tokens) }} in &middot; {{ number_format((int) $message->output_tokens) }} out @endif@if ($message->cost_usd !== null) &middot; ~${{ number_format($message->cost_usd, 4) }} @endif
												</span>
												<button type="button" wire:click="saveToLibrary({{ $message->id }})" wire:loading.attr="disabled" wire:target="saveToLibrary({{ $message->id }})"
													class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--sb-accent)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--sb-accent)] transition hover:bg-[var(--sb-accent)]/20 disabled:cursor-not-allowed disabled:opacity-40">
													<x-heroicon-o-bookmark class="h-4 w-4" />
													<span wire:loading.remove wire:target="saveToLibrary({{ $message->id }})">Save to library</span>
													<span wire:loading wire:target="saveToLibrary({{ $message->id }})">Saving&hellip;</span>
												</button>
											</div>
										@elseif ($message->isProcessing())
											<div class="flex aspect-[3/2] w-full flex-col items-center justify-center gap-2 text-stone-400">
												<svg class="h-6 w-6 animate-spin text-[var(--sb-accent)]" viewBox="0 0 24 24" fill="none">
													<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
													<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
												</svg>
												<span class="text-xs">Dreaming up your image&hellip;</span>
											</div>
										@else
											<div class="flex aspect-[3/2] w-full flex-col items-center justify-center gap-1 px-4 text-center text-red-600">
												<x-heroicon-o-exclamation-triangle class="h-6 w-6" />
												<span class="text-xs font-medium">Couldn't generate that</span>
												@if ($message->error)
													<span class="line-clamp-2 text-[11px] text-stone-500">{{ $message->error }}</span>
												@endif
												<button type="button" wire:click="retry({{ $message->id }})" wire:loading.attr="disabled" wire:target="retry({{ $message->id }})"
													class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-[var(--sb-accent)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--sb-accent)] transition hover:bg-[var(--sb-accent)]/20 disabled:cursor-not-allowed disabled:opacity-40">
													<x-heroicon-o-arrow-path class="h-4 w-4" />
													Retry
												</button>
											</div>
										@endif
									</div>
								</div>
							@endif
						</div>
					@endforeach
				</div>
			@else
				<div class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center">
					<x-heroicon-o-sparkles class="mx-auto h-8 w-8 text-stone-300" />
					<p class="mt-3 font-display text-lg text-stone-500">Describe the frame you want.</p>
					<p class="mt-1 text-sm text-stone-400">Start from a prompt, or upload a photo to transform. Refine it with follow-up messages until it's just right.</p>
					@if (! empty($examplePrompts))
						<div class="mt-5 flex flex-wrap justify-center gap-2">
							@foreach ($examplePrompts as $index => $example)
								<button type="button" wire:click="useExample({{ $index }})"
									class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs text-stone-500 transition hover:border-[var(--sb-accent)]/50 hover:text-[var(--sb-accent)]">
									{{ $example['label'] }}
								</button>
							@endforeach
						</div>
					@endif
				</div>
			@endif

			{{-- Composer --}}
			<form wire:submit="send" class="mt-8 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">

				@if ($conversation === null)
					<div class="mb-4 flex flex-wrap items-center gap-2">
						<span class="text-xs font-medium uppercase tracking-wider text-stone-400">Shape</span>
						@foreach (['landscape' => 'Landscape', 'portrait' => 'Portrait', 'square' => 'Square'] as $value => $label)
							<button type="button" wire:click="$set('aspect', '{{ $value }}')"
								class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $aspect === $value ? 'bg-[var(--sb-accent)] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
								{{ $label }}
							</button>
						@endforeach
					</div>
				@endif

				<div class="flex items-start gap-3">
					<label class="group relative flex h-16 w-16 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-stone-300 bg-stone-50 transition hover:border-[var(--sb-accent)]/60"
						title="Optional starting image">
						@if ($startImage)
							<img src="{{ $startImage->temporaryUrl() }}" alt="Base" class="absolute inset-0 h-full w-full object-cover" />
						@elseif ($fromImage)
							<img src="{{ $fromImage->url }}" alt="Base" class="absolute inset-0 h-full w-full object-cover" />
						@else
							<x-heroicon-o-photo class="h-5 w-5 text-stone-300 transition group-hover:text-[var(--sb-accent)]" />
						@endif
						<input type="file" wire:model="startImage" accept="image/*" class="absolute inset-0 cursor-pointer opacity-0" />
					</label>

					<div class="min-w-0 flex-1">
						<textarea wire:model="prompt" rows="3" placeholder="{{ $conversation ? 'Describe a change — “make the sky more golden”…' : 'A misty canal at dawn, impressionist oil painting…' }}"
							x-data="{ resize() { $el.style.height = '0px'; $el.style.height = Math.min(Math.max($el.scrollHeight, 78), 240) + 'px'; } }"
							x-init="$nextTick(() => resize())"
							x-on:input="resize()"
							x-on:prompt-changed.window="$nextTick(() => resize())"
							class="min-h-[78px] max-h-[240px] w-full resize-none overflow-y-auto rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 placeholder-stone-400 focus:border-[var(--sb-accent)] focus:outline-none focus:ring-1 focus:ring-[var(--sb-accent)]/40"></textarea>
						<div class="mt-1 flex items-center justify-between">
							<span class="text-xs text-stone-400">
								<span wire:loading wire:target="startImage" class="text-[var(--sb-accent)]">Uploading image&hellip;</span>
								<span wire:loading.remove wire:target="startImage">{{ $conversation ? 'Editing your latest image' : ($fromImage ? 'Editing an image from your library' : 'A photo above is optional') }}</span>
							</span>
							<div class="flex items-center gap-2">
								<button type="button" wire:click="improvePrompt" wire:loading.attr="disabled" wire:target="improvePrompt" title="Improve the prompt with AI"
									class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 px-4 py-2 text-sm font-medium text-stone-600 transition hover:border-[var(--sb-accent)]/50 hover:text-[var(--sb-accent)] disabled:cursor-not-allowed disabled:opacity-50">
									<x-heroicon-o-sparkles class="h-4 w-4" />
									<span wire:loading.remove wire:target="improvePrompt">Improve</span>
									<span wire:loading wire:target="improvePrompt">Improving&hellip;</span>
								</button>
								<button type="submit" wire:loading.attr="disabled" wire:target="send, startImage"
									class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--sb-accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[var(--sb-accent-hover)] disabled:cursor-not-allowed disabled:opacity-50">
									<x-heroicon-o-paper-airplane class="h-4 w-4" />
									<span wire:loading.remove wire:target="send">{{ $conversation ? 'Refine' : 'Generate' }}</span>
									<span wire:loading wire:target="send">Working&hellip;</span>
								</button>
							</div>
						</div>
						@error('prompt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
						@error('startImage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
					</div>
				</div>
			</form>
		</section>
	</div>

	{{-- Save to library: optimize preset picker --}}
	@if ($savingMessageId !== null)
		<div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.cancelSaveToLibrary()">
			<div class="absolute inset-0 bg-stone-900/40" wire:click="cancelSaveToLibrary"></div>
			<div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
				<h2 class="font-display text-xl font-semibold text-stone-900">Optimize for the frame</h2>
				<p class="mt-1 text-sm text-stone-500">Pick how to prepare this image for the e-ink panel &mdash; you can tune or add presets in config.</p>
				<div class="mt-5 space-y-2">
					@foreach ($optimizerPresets as $key => $preset)
						<button type="button" wire:click="confirmSaveToLibrary('{{ $key }}')" wire:loading.attr="disabled" wire:target="confirmSaveToLibrary"
							class="flex w-full items-start gap-3 rounded-xl border border-stone-200 p-4 text-left transition hover:border-[var(--sb-accent)]/60 hover:bg-[var(--sb-accent)]/[0.03] disabled:cursor-not-allowed disabled:opacity-50">
							<x-heroicon-o-sparkles class="mt-0.5 h-5 w-5 shrink-0 text-[var(--sb-accent)]" />
							<span class="min-w-0">
								<span class="block text-sm font-semibold text-stone-900">{{ $preset['label'] ?? $key }}</span>
								@if (! empty($preset['description']))
									<span class="mt-0.5 block text-xs text-stone-500">{{ $preset['description'] }}</span>
								@endif
							</span>
						</button>
					@endforeach
				</div>
				<div class="mt-5 flex justify-end">
					<button type="button" wire:click="cancelSaveToLibrary" class="rounded-lg px-4 py-2 text-sm font-medium text-stone-500 transition hover:text-stone-800">Cancel</button>
				</div>
			</div>
		</div>
	@endif

	{{-- Toast --}}
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
