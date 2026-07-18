<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('switchbot_ai_conversations', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('user_id')->nullable()->index();
			$table->string('title')->nullable();
			$table->string('aspect')->default('landscape');
			$table->timestamps();

			$table->index('created_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('switchbot_ai_conversations');
	}
};
