<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SpaanProductions\LaravelSwitchbotFrame\Models\FrameImage;
use SpaanProductions\LaravelSwitchbotFrame\Enums\FrameImageStatus;

/**
 * @extends Factory<FrameImage>
 */
class FrameImageFactory extends Factory
{
	protected $model = FrameImage::class;

	public function definition(): array
	{
		return [
			'title' => fake()->words(3, true),
			'original_name' => fake()->word() . '.jpg',
			'path' => 'switchbot/frame-images/' . fake()->uuid() . '.jpg',
			'width' => 1600,
			'height' => 1200,
			'file_size' => fake()->numberBetween(100_000, 900_000),
			'optimized' => true,
			'status' => FrameImageStatus::Ready,
			'error' => null,
			'push_count' => 0,
			'last_pushed_at' => null,
		];
	}

	public function pushed(): static
	{
		return $this->state(fn (): array => [
			'push_count' => fake()->numberBetween(1, 5),
			'last_pushed_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
		]);
	}

	public function processing(): static
	{
		return $this->state(fn (): array => [
			'status' => FrameImageStatus::Processing,
			'width' => null,
			'height' => null,
			'file_size' => null,
		]);
	}

	public function failed(): static
	{
		return $this->state(fn (): array => [
			'status' => FrameImageStatus::Failed,
			'error' => 'The file could not be processed.',
			'width' => null,
			'height' => null,
			'file_size' => null,
		]);
	}
}
