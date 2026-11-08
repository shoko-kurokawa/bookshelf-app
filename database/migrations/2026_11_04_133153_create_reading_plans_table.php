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
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();

            $table->date('target_date');

            $table->string('status', 20)
                ->default('in_progress');

            $table->dateTime('completed_at')
                ->nullable();

            $table->timestamps();

            // ユーザー別・書籍別の計画検索を効率化
            $table->index(['user_id', 'book_id']);

            // 状態・目標日による通知対象の検索を効率化
            $table->index(['status', 'target_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};