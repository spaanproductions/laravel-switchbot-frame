<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	public function up(): void
	{
		Schema::table('switchbot_ai_messages', function (Blueprint $table) {
			$table->string('model')->nullable()->after('output_tokens');
			$table->decimal('cost_usd', 12, 6)->nullable()->after('model');
		});
	}

	public function down(): void
	{
		Schema::table('switchbot_ai_messages', function (Blueprint $table) {
			$table->dropColumn(['model', 'cost_usd']);
		});
	}
};
