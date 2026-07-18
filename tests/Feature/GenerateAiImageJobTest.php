<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Tests\Feature;

use Laravel\Ai\Image;
use RuntimeException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SpaanProductions\LaravelSwitchbotFrame\Tests\TestCase;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiMessage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\AiImageStatus;
use SpaanProductions\LaravelSwitchbotFrame\Models\AiConversation;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\CalculateAiCostJob;
use SpaanProductions\LaravelSwitchbotFrame\Jobs\GenerateAiImageJob;

class GenerateAiImageJobTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_generates_stores_the_image_and_marks_it_ready(): void
	{
		Image::fake([base64_encode($this->pngBytes(8, 6))]);

		$conversation = AiConversation::factory()->create(['aspect' => 'landscape']);
		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'a red cabin in the snow'))->handle();

		$message->refresh();

		$this->assertSame(AiImageStatus::Ready, $message->status);
		$this->assertNotNull($message->image_path);
		$this->assertSame(8, $message->width);
		$this->assertSame(6, $message->height);
		$this->assertGreaterThan(0, $message->file_size);
		$this->assertNull($message->error);

		Storage::disk('s3')->assertExists($message->image_path);

		Image::assertGenerated(fn ($prompt): bool => $prompt->prompt === 'a red cabin in the snow');
	}

	public function test_it_attaches_the_source_image_when_editing(): void
	{
		Image::fake([base64_encode($this->pngBytes(8, 6))]);

		$conversation = AiConversation::factory()->create();
		$source = 'switchbot/ai-conversations/' . $conversation->id . '/prev.png';
		Storage::disk('s3')->put($source, $this->pngBytes(8, 6));

		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'make it warmer', $source))->handle();

		$this->assertTrue($message->fresh()->isReady());

		Image::assertGenerated(fn ($prompt): bool => count($prompt->attachments) === 1);
	}

	public function test_it_stores_the_reported_token_usage(): void
	{
		$response = new ImageResponse(
			collect([new GeneratedImage(base64_encode($this->pngBytes(8, 6)), 'image/png')]),
			new Usage(promptTokens: 1290, completionTokens: 42),
			new Meta('gemini', 'gemini-2.5-flash-image'),
		);

		Image::fake([$response]);

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'a red cabin'))->handle();

		$message->refresh();

		$this->assertSame(1290, $message->input_tokens);
		$this->assertSame(42, $message->output_tokens);
		$this->assertSame(1332, $message->totalTokens());
	}

	public function test_it_stores_the_model_and_queues_cost_estimation_when_enabled(): void
	{
		config(['switchbot.ai.cost_estimation.enabled' => true]);
		Queue::fake();

		Image::fake([new ImageResponse(
			collect([new GeneratedImage(base64_encode($this->pngBytes(8, 6)), 'image/png')]),
			new Usage(promptTokens: 10, completionTokens: 5),
			new Meta('gemini', 'gemini-2.5-flash-image'),
		)]);

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'a cabin'))->handle();

		$this->assertSame('gemini-2.5-flash-image', $message->fresh()->model);

		Queue::assertPushed(CalculateAiCostJob::class);
	}

	public function test_it_does_not_queue_cost_estimation_when_disabled(): void
	{
		config(['switchbot.ai.cost_estimation.enabled' => false]);
		Queue::fake();

		Image::fake([base64_encode($this->pngBytes(8, 6))]);

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'a cabin'))->handle();

		Queue::assertNotPushed(CalculateAiCostJob::class);
	}

	public function test_it_marks_the_message_failed_when_generation_throws(): void
	{
		Image::fake(fn () => throw new RuntimeException('provider exploded'));

		$conversation = AiConversation::factory()->create();
		$message = AiMessage::factory()->processing()->create(['ai_conversation_id' => $conversation->id]);

		(new GenerateAiImageJob($message, 'a cat'))->handle();

		$message->refresh();

		$this->assertSame(AiImageStatus::Failed, $message->status);
		$this->assertNotNull($message->error);
		$this->assertNull($message->image_path);
	}

	private function pngBytes(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);

		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}
}
