<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Laravel\Ai\Ai;
use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;
use SpaanProductions\LaravelSwitchbotFrame\Livewire\AiStudio;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiMessageRole;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\CalculateAiCostJob;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\GenerateAiImageJob;
use SpaanProductions\LaravelSwitchbotFrame\Ai\Agents\PromptImprover;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\OptimizeFrameImageJob;

class AiStudioTest extends TestCase
{
	use RefreshDatabase;

	public function test_sending_a_prompt_creates_messages_and_dispatches_generation(): void
	{
		Queue::fake();

		Livewire::test(AiStudio::class)
			->set('prompt', 'a misty canal at dawn')
			->call('send')
			->assertSet('prompt', '');

		$conversation = AiConversation::query()->first();

		$this->assertNotNull($conversation);
		$this->assertSame('a misty canal at dawn', $conversation->title);
		$this->assertCount(2, $conversation->messages);
		$this->assertTrue($conversation->messages[0]->isUser());
		$this->assertTrue($conversation->messages[1]->isAssistant());
		$this->assertTrue($conversation->messages[1]->isProcessing());

		Queue::assertPushed(GenerateAiImageJob::class);
	}

	public function test_a_start_image_is_stored_and_used_as_the_source(): void
	{
		Queue::fake();

		Livewire::test(AiStudio::class)
			->set('prompt', 'turn this into an oil painting')
			->set('startImage', UploadedFile::fake()->image('base.jpg', 120, 120))
			->call('send');

		$userMessage = AiMessage::query()->where('role', AiMessageRole::User)->firstOrFail();

		$this->assertNotNull($userMessage->image_path);
		$this->assertStringContainsString('/uploads/', $userMessage->image_path);
		Storage::disk('s3')->assertExists($userMessage->image_path);

		Queue::assertPushed(GenerateAiImageJob::class, fn (GenerateAiImageJob $job): bool => $job->sourcePath === $userMessage->image_path);
	}

	public function test_a_follow_up_prompt_edits_the_latest_generated_image(): void
	{
		Queue::fake();

		$conversation = AiConversation::factory()->create();
		$previous = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/prev.png',
		]);

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->set('prompt', 'make the water calmer')
			->call('send');

		Queue::assertPushed(GenerateAiImageJob::class, fn (GenerateAiImageJob $job): bool => $job->sourcePath === $previous->image_path);
	}

	public function test_saving_to_the_library_opens_the_optimize_preset_dialog(): void
	{
		Queue::fake();

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/final.png',
		]);
		Storage::disk('s3')->put($message->image_path, 'png-bytes');

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->call('saveToLibrary', $message->id)
			->assertSet('savingMessageId', $message->id);

		// Nothing is saved until a preset is chosen.
		$this->assertSame(0, FrameImage::query()->count());
		Queue::assertNotPushed(OptimizeFrameImageJob::class);
	}

	public function test_confirming_a_preset_saves_and_optimizes_with_that_preset(): void
	{
		Queue::fake();

		$conversation = AiConversation::factory()->create(['title' => 'Canal at dawn']);
		$message = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/final.png',
		]);
		Storage::disk('s3')->put($message->image_path, 'png-bytes');

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->call('saveToLibrary', $message->id)
			->call('confirmSaveToLibrary', 'soft')
			->assertSet('savingMessageId', null);

		$frame = FrameImage::query()->first();

		$this->assertNotNull($frame);
		$this->assertSame('Canal at dawn', $frame->title);
		$this->assertSame(FrameImageStatus::Processing, $frame->status);

		Queue::assertPushed(OptimizeFrameImageJob::class, fn (OptimizeFrameImageJob $job): bool => is_array($job->optimizer) && $job->optimizer['brightness'] === -8);
	}

	public function test_deleting_a_conversation_removes_its_generated_assets(): void
	{
		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/final.png',
		]);
		Storage::disk('s3')->put($message->image_path, 'png-bytes');

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->call('deleteConversation', $conversation->id)
			->assertSet('conversationId', null);

		Storage::disk('s3')->assertMissing($message->image_path);
		$this->assertDatabaseMissing('switchbot_ai_conversations', ['id' => $conversation->id]);
		$this->assertDatabaseMissing('switchbot_ai_messages', ['id' => $message->id]);
	}

	public function test_history_only_shows_the_current_users_conversations(): void
	{
		$user = new class extends Authenticatable {
			public function getAuthIdentifier(): int
			{
				return 7;
			}
		};

		$this->actingAs($user);

		$mine = AiConversation::factory()->create(['user_id' => 7, 'title' => 'Mine']);
		$theirs = AiConversation::factory()->create(['user_id' => 99, 'title' => 'Theirs']);

		Livewire::test(AiStudio::class)
			->assertViewHas('history', fn ($history): bool => $history->pluck('id')->contains($mine->id)
				&& ! $history->pluck('id')->contains($theirs->id));
	}

	public function test_retrying_a_failed_message_redispatches_generation(): void
	{
		Queue::fake();

		$conversation = AiConversation::factory()->create();
		AiMessage::factory()->create([
			'ai_conversation_id' => $conversation->id,
			'role' => AiMessageRole::User,
			'prompt' => 'a red cabin in the snow',
		]);
		$failed = AiMessage::factory()->failed()->create([
			'ai_conversation_id' => $conversation->id,
		]);

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->call('retry', $failed->id);

		$this->assertTrue($failed->fresh()->isProcessing());

		Queue::assertPushed(GenerateAiImageJob::class, fn (GenerateAiImageJob $job): bool => $job->message->is($failed) && $job->prompt === 'a red cabin in the snow');
	}

	public function test_a_follow_up_edits_the_most_recent_image_not_the_first(): void
	{
		Queue::fake();

		$conversation = AiConversation::factory()->create();
		AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/first.png',
		]);
		$latest = AiMessage::factory()->assistant()->create([
			'ai_conversation_id' => $conversation->id,
			'image_path' => 'switchbot/ai-conversations/' . $conversation->id . '/latest.png',
		]);

		Livewire::test(AiStudio::class)
			->set('conversationId', $conversation->id)
			->set('prompt', 'make it brighter')
			->call('send');

		Queue::assertPushed(GenerateAiImageJob::class, fn (GenerateAiImageJob $job): bool => $job->sourcePath === $latest->image_path);
	}

	public function test_a_conversation_from_the_url_is_loaded_on_mount(): void
	{
		$conversation = AiConversation::factory()->create();

		Livewire::withQueryParams(['conversation' => $conversation->id])
			->test(AiStudio::class)
			->assertSet('conversationId', $conversation->id)
			->assertViewHas('conversation', fn ($current): bool => $current !== null && $current->id === $conversation->id);
	}

	public function test_starting_from_a_library_image_uses_it_as_the_source(): void
	{
		Queue::fake();

		$frame = FrameImage::factory()->create();
		Storage::disk('s3')->put($frame->path, 'png-bytes');

		Livewire::withQueryParams(['from' => $frame->id])
			->test(AiStudio::class)
			->set('prompt', 'render this as an oil painting')
			->call('send')
			->assertSet('fromFrameImageId', null);

		$userMessage = AiMessage::query()->where('role', AiMessageRole::User)->firstOrFail();
		$this->assertSame($frame->path, $userMessage->image_path);

		Queue::assertPushed(GenerateAiImageJob::class, fn (GenerateAiImageJob $job): bool => $job->sourcePath === $frame->path);
	}

	public function test_improving_the_prompt_replaces_it_with_the_ai_suggestion(): void
	{
		$suggestion = 'A luminous misty canal at dawn, soft impressionist oil painting bathed in golden light.';

		Ai::fakeAgent(PromptImprover::class, [$suggestion]);

		Livewire::test(AiStudio::class)
			->set('prompt', 'canal')
			->call('improvePrompt')
			->assertSet('prompt', $suggestion)
			->assertDispatched('prompt-changed');
	}

	public function test_improving_the_prompt_logs_it_and_queues_cost_when_enabled(): void
	{
		config(['switchbot.ai.cost_estimation.enabled' => true]);
		Queue::fake();

		$suggestion = 'A luminous misty canal at dawn, soft impressionist oil painting.';
		Ai::fakeAgent(PromptImprover::class, [$suggestion]);

		Livewire::test(AiStudio::class)
			->set('prompt', 'canal')
			->call('improvePrompt');

		$this->assertDatabaseHas('switchbot_ai_prompt_improvements', [
			'input_prompt' => 'canal',
			'output_prompt' => $suggestion,
		]);

		Queue::assertPushed(CalculateAiCostJob::class);
	}

	public function test_using_an_example_fills_a_detailed_prompt(): void
	{
		Livewire::test(AiStudio::class)
			->call('useExample', 0)
			->assertDispatched('prompt-changed')
			->assertSet('prompt', fn (string $prompt): bool => str_starts_with($prompt, 'A misty canal at dawn') && strlen($prompt) > 120);
	}

	public function test_the_shape_defaults_to_the_now_showing_orientation(): void
	{
		config(['switchbot.ai.images.default_aspect' => null, 'switchbot.aspect.now_showing' => 'square']);

		Livewire::test(AiStudio::class)->assertSet('aspect', 'square');
	}

	public function test_an_inaccessible_conversation_from_the_url_is_ignored(): void
	{
		$user = new class extends Authenticatable {
			public function getAuthIdentifier(): int
			{
				return 7;
			}
		};

		$this->actingAs($user);

		$other = AiConversation::factory()->create(['user_id' => 99]);

		Livewire::withQueryParams(['conversation' => $other->id])
			->test(AiStudio::class)
			->assertSet('conversationId', null);
	}
}
