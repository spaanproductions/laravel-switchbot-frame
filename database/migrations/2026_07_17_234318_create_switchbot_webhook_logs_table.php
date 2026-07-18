<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('switchbot_webhook_logs', function (Blueprint $table) {
			$table->id();
			$table->json('payload');
			$table->timestamps();

			$table->index('created_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('switchbot_webhook_logs');
	}
};
