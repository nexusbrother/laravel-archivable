<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_monthly_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_test_model_id')
                ->constrained('monthly_test_models'); // 可选：用户删除时，关联的 posts 记录自动删除

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_monthly_models');
    }
};
