<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('switchbot_ai_messages', function (Blueprint $table) {
			$table->id();
			$table->foreignId('ai_conversation_id')
				->constrained('switchbot_ai_conversations')
				->cascadeOnDelete();
			$table->string('role');
			$table->text('prompt')->nullable();
			$table->string('image_path')->nullable();
			$table->unsignedInteger('width')->nullable();
			$table->unsignedInteger('height')->nullable();
			$table->unsignedBigInteger('file_size')->nullable();
			$table->unsignedInteger('input_tokens')->nullable();
			$table->unsignedInteger('output_tokens')->nullable();
			$table->string('status')->default('ready');
			$table->text('error')->nullable();
			$table->timestamps();

			$table->index(['ai_conversation_id', 'id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('switchbot_ai_messages');
	}
};
