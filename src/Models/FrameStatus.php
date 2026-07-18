<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SpaanProductions\LaravelSwitchbotFrame\Database\Factories\FrameStatusFactory;

/**
 * @property int $id
 * @property string|null $device_mac
 * @property int|null $battery
 * @property string|null $display_mode
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FrameStatus extends Model
{
	/** @use HasFactory<FrameStatusFactory> */
	use HasFactory;

	protected $fillable = [
		'device_mac',
		'battery',
		'display_mode',
		'received_at',
	];

	protected static function newFactory(): FrameStatusFactory
	{
		return FrameStatusFactory::new();
	}

	/** @return array<string, string> */
	protected function casts(): array
	{
		return [
			'received_at' => 'datetime',
			'battery' => 'integer',
		];
	}
}
