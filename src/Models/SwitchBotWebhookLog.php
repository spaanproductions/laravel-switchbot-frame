<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\SwitchBotWebhookLogFactory;

/**
 * @property int $id
 * @property array<string, mixed> $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SwitchBotWebhookLog extends Model
{
	/** @use HasFactory<SwitchBotWebhookLogFactory> */
	use HasFactory;

	private const int MAX_ROWS = 500;

	protected $table = 'switchbot_webhook_logs';

	protected $fillable = [
		'payload',
	];

	protected static function newFactory(): SwitchBotWebhookLogFactory
	{
		return SwitchBotWebhookLogFactory::new();
	}

	/** @return array<string, string> */
	protected function casts(): array
	{
		return [
			'payload' => 'array',
		];
	}

	/**
	 * Store a webhook body, keeping only the most recent rows so the debug log
	 * can never grow unbounded.
	 *
	 * @param  array<string, mixed>  $payload
	 */
	public static function record(array $payload): void
	{
		self::query()->create(['payload' => $payload]);

		$cutoff = self::query()->orderByDesc('id')->skip(self::MAX_ROWS)->limit(1)->value('id');

		if ($cutoff !== null) {
			self::query()->where('id', '<=', $cutoff)->delete();
		}
	}
}
