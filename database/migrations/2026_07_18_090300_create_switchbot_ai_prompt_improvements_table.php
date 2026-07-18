<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('switchbot_ai_prompt_improvements', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('user_id')->nullable()->index();
			$table->text('input_prompt');
			$table->text('output_prompt');
			$table->string('model')->nullable();
			$table->unsignedInteger('input_tokens')->nullable();
			$table->unsignedInteger('output_tokens')->nullable();
			$table->decimal('cost_usd', 12, 6)->nullable();
			$table->timestamps();

			$table->index('created_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('switchbot_ai_prompt_improvements');
	}
};
