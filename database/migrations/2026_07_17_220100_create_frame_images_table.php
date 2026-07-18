<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('frame_images', function (Blueprint $table) {
			$table->id();
			$table->string('title')->nullable();
			$table->string('original_name');
			$table->string('path')->unique();
			$table->unsignedInteger('width')->nullable();
			$table->unsignedInteger('height')->nullable();
			$table->unsignedBigInteger('file_size')->nullable();
			$table->boolean('optimized')->default(true);
			$table->string('status')->default('ready');
			$table->text('error')->nullable();
			$table->unsignedInteger('push_count')->default(0);
			$table->timestamp('last_pushed_at')->nullable();
			$table->timestamps();

			$table->index('created_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('frame_images');
	}
};
