<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Livewire;

use Throwable;
use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFactory;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiMessageRole;
use SpaanProductions\LaravelSwitchbotFrame\Support\ImageStudio;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\CalculateAiCostJob;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\GenerateAiImageJob;
use SpaanProductions\LaravelSwitchbotFrame\Ai\Agents\PromptImprover;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\OptimizeFrameImageJob;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiPromptImprovement;

#[Layout('switchbot::layout')]
class AiStudio extends Component
{
	use WithFileUploads;

	/**
	 * Example starters: a short `label` for the button, and a richer `prompt`
	 * (with subject, style, light, mood and composition) that produces a better
	 * image and is inserted into the input for the user to tweak.
	 *
	 * @var list<array{label: string, prompt: string}>
	 */
	private const array EXAMPLE_PROMPTS = [
		[
			'label' => 'A misty canal at dawn, impressionist oil painting',
			'prompt' => 'A misty canal at dawn in an old European town, loose impressionist oil painting. Soft golden light filtering through fog, calm water mirroring pastel townhouses, a muted palette of teal and warm amber, visible brushstrokes, and a peaceful contemplative mood. Wide, balanced composition.',
		],
		[
			'label' => 'A cozy autumn forest path, soft watercolour',
			'prompt' => 'A cozy forest path in mid-autumn, delicate soft watercolour. A winding trail carpeted with fallen leaves, tall trees in amber, rust and ochre, gentle dappled sunlight and light morning haze, a warm nostalgic atmosphere, soft edges and subtle paper texture.',
		],
		[
			'label' => 'A minimalist mountain landscape at sunset, warm tones',
			'prompt' => 'A minimalist mountain landscape at sunset with clean flat shapes and generous negative space. Layered ridgelines fading into haze, a low sun and a gradient sky of coral, peach and soft violet, a calm modern mood, a limited warm palette and an elegant, uncluttered composition.',
		],
		[
			'label' => 'A vintage botanical illustration of wildflowers',
			'prompt' => 'A vintage botanical illustration of assorted wildflowers in the style of a 19th-century field guide. Precise hand-drawn linework with delicate watercolour washes, poppies, cornflowers and daisies with leaves and seed heads, an aged ivory background, muted natural colours and refined scientific elegance.',
		],
		[
			'label' => 'A serene Japanese garden in the rain, ink wash style',
			'prompt' => 'A serene Japanese garden in gentle rain, traditional sumi-e ink wash style. A stone lantern, arched bridge and koi pond framed by maples, soft grey gradients with restrained hints of green and vermilion, misty depth, a quiet meditative mood, expressive brushwork and plenty of empty space.',
		],
		[
			'label' => 'A starry night over rolling hills, post-impressionist',
			'prompt' => 'A starry night over rolling countryside hills in an expressive post-impressionist style. A swirling luminous sky with a glowing moon and thick energetic brushstrokes, deep indigo and cobalt blues warmed by golden starlight, a sleeping village below, and a dramatic yet dreamy romantic mood.',
		],
	];

	#[Url(as: 'conversation')]
	public ?int $conversationId = null;

	#[Url(as: 'from')]
	public ?int $fromFrameImageId = null;

	public string $prompt = '';

	public string $aspect = 'landscape';

	#[Validate(['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'])]
	public ?TemporaryUploadedFile $startImage = null;

	public ?int $savingMessageId = null;

	public ?string $notice = null;

	public string $noticeType = 'success';

	public function mount(): void
	{
		$this->aspect = ImageStudio::defaultAspect();

		// A conversation id can arrive from the URL (page refresh / shared link);
		// drop it when it isn't one of the current user's conversations.
		if ($this->conversationId !== null && ! $this->conversations()->whereKey($this->conversationId)->exists()) {
			$this->conversationId = null;
		}

		// A frame image to start from can arrive from the library ("Edit with AI");
		// drop it if the image no longer exists or isn't ready.
		if ($this->fromFrameImageId !== null && ! FrameImage::query()->whereKey($this->fromFrameImageId)->where('status', FrameImageStatus::Ready)->exists()) {
			$this->fromFrameImageId = null;
		}
	}

	public function send(): void
	{
		$this->validate([
			'prompt' => ['required', 'string', 'max:1000'],
			'startImage' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
			'aspect' => ['required', 'in:portrait,landscape,square'],
		]);

		$conversation = $this->currentConversation() ?? $this->startConversation();

		$firstTurn = $conversation->messages()->doesntExist();

		$sourcePath = $this->resolveSourcePath($conversation, $firstTurn);

		// Record the base image on the user turn only when it's a *new* base (an
		// upload or a library image), not the carried-over previous result.
		$baseImage = ($this->startImage !== null || ($firstTurn && $this->fromFrameImageId !== null))
			? $sourcePath
			: null;

		$conversation->messages()->create([
			'role' => AiMessageRole::User,
			'prompt' => $this->prompt,
			'image_path' => $baseImage,
			'status' => AiImageStatus::Ready,
		]);

		$assistant = $conversation->messages()->create([
			'role' => AiMessageRole::Assistant,
			'status' => AiImageStatus::Processing,
		]);

		$conversation->touch();

		GenerateAiImageJob::dispatch($assistant, $this->prompt, $sourcePath);

		$this->reset('prompt', 'startImage');
		$this->fromFrameImageId = null;
	}

	public function useExample(int $index): void
	{
		$prompt = self::EXAMPLE_PROMPTS[$index]['prompt'] ?? null;

		if ($prompt !== null) {
			$this->prompt = $prompt;
			$this->dispatch('prompt-changed');
		}
	}

	public function improvePrompt(): void
	{
		$this->validate(['prompt' => ['required', 'string', 'max:1000']]);

		$original = $this->prompt;

		try {
			$response = (new PromptImprover)->prompt($original);
		} catch (Throwable) {
			$this->flash('Could not improve the prompt right now.', 'error');

			return;
		}

		$improved = trim($response->text);

		if ($improved === '') {
			return;
		}

		$log = AiPromptImprovement::create([
			'user_id' => Auth::id(),
			'input_prompt' => $original,
			'output_prompt' => $improved,
			'model' => $response->meta->model,
			'input_tokens' => $response->usage->promptTokens ?: null,
			'output_tokens' => $response->usage->completionTokens ?: null,
		]);

		if (ImageStudio::costEstimationEnabled()) {
			CalculateAiCostJob::dispatch($log);
		}

		$this->prompt = Str::limit($improved, 1000, '');
		$this->dispatch('prompt-changed');
	}

	public function saveToLibrary(int $messageId): void
	{
		$message = $this->currentConversation()?->messages()->whereKey($messageId)->first();

		if ($message === null || ! $message->hasImage()) {
			$this->flash('That image is not ready yet.', 'error');

			return;
		}

		// Open the optimize-preset dialog; the save happens in confirmSaveToLibrary().
		$this->savingMessageId = $messageId;
	}

	public function cancelSaveToLibrary(): void
	{
		$this->savingMessageId = null;
	}

	public function confirmSaveToLibrary(string $preset): void
	{
		$conversation = $this->currentConversation();
		$message = $conversation?->messages()->whereKey($this->savingMessageId)->first();
		$settings = ImageStudio::optimizerPresets()[$preset] ?? null;

		if ($message === null || ! $message->hasImage() || $settings === null) {
			$this->savingMessageId = null;
			$this->flash('That image could not be saved.', 'error');

			return;
		}

		$disk = config('switchbot.disk');
		$incomingPath = 'switchbot/frame-images/incoming/' . Str::uuid() . '.png';

		Storage::disk($disk)->put(
			$incomingPath,
			Storage::disk($disk)->get($message->image_path),
			config('switchbot.disk_visibility') ?: [],
		);

		$image = FrameImage::create([
			'title' => $conversation->title,
			'original_name' => 'ai-studio-' . $message->id . '.png',
			'path' => 'switchbot/frame-images/' . Str::uuid() . '.jpg',
			'optimized' => true,
			'status' => FrameImageStatus::Processing,
		]);

		OptimizeFrameImageJob::dispatch($image, $incomingPath, true, $settings);

		$this->savingMessageId = null;
		$this->flash('Saved to your library — optimizing for the panel now.');
	}

	public function retry(int $messageId): void
	{
		$conversation = $this->currentConversation();

		$message = $conversation?->messages()->whereKey($messageId)->first();

		if ($message === null || ! $message->isAssistant() || ! $message->isFailed()) {
			return;
		}

		// The prompt lives on the user message that preceded this assistant turn.
		$userMessage = $conversation->messages()
			->where('role', AiMessageRole::User)
			->where('id', '<', $message->id)
			->reorder('id', 'desc')
			->first();

		if ($userMessage === null || blank($userMessage->prompt)) {
			$this->flash('There is nothing to retry.', 'error');

			return;
		}

		// Reuse the same source: the turn's uploaded image, else the latest image
		// generated before this turn.
		$sourcePath = $userMessage->image_path ?? $conversation->messages()
			->where('role', AiMessageRole::Assistant)
			->where('status', AiImageStatus::Ready)
			->where('id', '<', $message->id)
			->whereNotNull('image_path')
			->reorder('id', 'desc')
			->value('image_path');

		$message->update(['status' => AiImageStatus::Processing, 'error' => null]);

		GenerateAiImageJob::dispatch($message, $userMessage->prompt, $sourcePath);
	}

	public function loadConversation(int $id): void
	{
		if ($this->conversations()->whereKey($id)->exists()) {
			$this->conversationId = $id;
			$this->reset('prompt', 'startImage');
		}
	}

	public function newConversation(): void
	{
		$this->reset('conversationId', 'prompt', 'startImage');
		$this->aspect = ImageStudio::defaultAspect();
	}

	public function deleteConversation(int $id): void
	{
		$conversation = $this->conversations()->whereKey($id)->first();

		if ($conversation === null) {
			return;
		}

		Storage::disk(config('switchbot.disk'))->deleteDirectory('switchbot/ai-conversations/' . $conversation->id);

		$conversation->messages()->delete();
		$conversation->delete();

		if ($this->conversationId === $id) {
			$this->conversationId = null;
		}

		$this->flash('Conversation and its generated images deleted.');
	}

	private function startConversation(): AiConversation
	{
		$conversation = AiConversation::create([
			'user_id' => Auth::id(),
			'title' => Str::limit($this->prompt, 60) ?: 'Untitled',
			'aspect' => $this->aspect,
		]);

		$this->conversationId = $conversation->id;

		return $conversation;
	}

	private function currentConversation(): ?AiConversation
	{
		if ($this->conversationId === null) {
			return null;
		}

		return $this->conversations()->whereKey($this->conversationId)->first();
	}

	/** @return Builder<AiConversation> */
	private function conversations(): Builder
	{
		return AiConversation::query()->where('user_id', Auth::id());
	}

	private function resolveSourcePath(AiConversation $conversation, bool $firstTurn): ?string
	{
		if ($this->startImage !== null) {
			$extension = $this->startImage->getClientOriginalExtension() ?: 'jpg';
			$path = 'switchbot/ai-conversations/' . $conversation->id . '/uploads/' . Str::uuid() . '.' . $extension;

			Storage::disk(config('switchbot.disk'))->put(
				$path,
				$this->startImage->get(),
				config('switchbot.disk_visibility') ?: [],
			);

			return $path;
		}

		if ($firstTurn && $this->fromFrameImageId !== null) {
			$frame = FrameImage::query()->whereKey($this->fromFrameImageId)->first();

			if ($frame?->isReady()) {
				return $frame->path;
			}
		}

		return $conversation->latestImageMessage()?->image_path;
	}

	private function flash(string $message, string $type = 'success'): void
	{
		$this->notice = $message;
		$this->noticeType = $type;

		$this->dispatch('notice-shown');
	}

	public function render(): View
	{
		$conversation = $this->currentConversation();

		return ViewFactory::make('switchbot::livewire.ai-studio', [
			'conversation' => $conversation,
			'messages' => $conversation?->messages()->with('conversation')->get() ?? collect(),
			'history' => $this->conversations()->latest('updated_at')->limit(30)->get(),
			'generating' => $conversation?->isProcessing() ?? false,
			'examplePrompts' => self::EXAMPLE_PROMPTS,
			'optimizerPresets' => ImageStudio::optimizerPresets(),
			'fromImage' => $conversation === null && $this->fromFrameImageId !== null
				? FrameImage::query()->whereKey($this->fromFrameImageId)->first()
				: null,
		]);
	}
}
