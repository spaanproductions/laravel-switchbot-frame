<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('frame_statuses', function (Blueprint $table) {
			$table->id();
			$table->string('device_mac')->nullable()->unique();
			$table->unsignedInteger('battery')->nullable();
			$table->string('display_mode')->nullable();
			$table->timestamp('received_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('frame_statuses');
	}
};
