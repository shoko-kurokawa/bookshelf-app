<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reading_plan_reminder_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reading_plan_id')
                ->constrained('reading_plans')
                ->cascadeOnDelete();

            $table->string('reminder_type', 30);

            $table->date('target_date');

            $table->timestamps();

            $table->unique(
                ['reading_plan_id', 'reminder_type', 'target_date'],
                'reading_plan_reminder_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_plan_reminder_logs');
    }
};