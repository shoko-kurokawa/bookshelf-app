<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 読書中の場合だけ書籍IDを返す仮想生成カラム
        DB::statement("
            ALTER TABLE reading_plans
            ADD COLUMN active_book_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status = 'in_progress' THEN book_id
                        ELSE NULL
                    END
                ) VIRTUAL
        ");

        // 読書中の計画だけ重複を禁止する
        DB::statement("
            CREATE UNIQUE INDEX reading_plans_active_unique
            ON reading_plans (user_id, active_book_id)
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX reading_plans_active_unique
            ON reading_plans
        ");

        DB::statement("
            ALTER TABLE reading_plans
            DROP COLUMN active_book_id
        ");
    }
};