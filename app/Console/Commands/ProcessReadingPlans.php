<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessReadingPlans extends Command
{
    /**
     * コマンド名
     */
    protected $signature = 'reading-plans:process';

    /**
     * コマンドの説明
     */
    protected $description = '読書計画の期限切れ判定とリマインダー通知を実行します';

    public function handle(): int
    {
        // 日本時間の本日
        $today = now('Asia/Tokyo')->startOfDay();

        // 1. 期限切れ判定
        $expiredCount = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->whereDate('target_date', '<', $today->toDateString())
            ->update([
                'status' => ReadingPlanStatus::Expired->value,
            ]);

        $this->info("期限切れに変更した計画: {$expiredCount} 件");

        // 2. 通知対象日と状態の組み合わせ
        $reminders = [
            [
                'type' => 'before_3_days',
                'target_date' => $today->copy()->addDays(3)->toDateString(),
                'status' => ReadingPlanStatus::InProgress->value,
            ],
            [
                'type' => 'on_target_date',
                'target_date' => $today->toDateString(),
                'status' => ReadingPlanStatus::InProgress->value,
            ],
            [
                'type' => 'after_3_days',
                'target_date' => $today->copy()->subDays(3)->toDateString(),
                'status' => ReadingPlanStatus::Expired->value,
            ],
        ];

        $notificationCount = 0;

        // 3. 各通知種別を処理
        foreach ($reminders as $reminder) {
            ReadingPlan::query()
                ->with(['user', 'book'])
                ->where('status', $reminder['status'])
                ->whereDate('target_date', $reminder['target_date'])
                ->orderBy('id')
                ->chunkById(100, function ($plans) use ($reminder, &$notificationCount) {
                    foreach ($plans as $plan) {
                        DB::transaction(function () use ($plan, $reminder, &$notificationCount) {
                            // 同時実行時の状態変更にも対応
                            $lockedPlan = ReadingPlan::query()
                                ->with(['user', 'book'])
                                ->lockForUpdate()
                                ->find($plan->id);

                            if (
                                $lockedPlan === null
                                || $lockedPlan->status->value !== $reminder['status']
                                || $lockedPlan->target_date->toDateString()
                                !== $reminder['target_date']
                            ) {
                                return;
                            }

                            // 同一計画・通知種別・目標日の重複を防止
                            $inserted = DB::table('reading_plan_reminder_logs')
                                ->insertOrIgnore([
                                    'reading_plan_id' => $lockedPlan->id,
                                    'reminder_type' => $reminder['type'],
                                    'target_date' => $reminder['target_date'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            if ($inserted === 0) {
                                return;
                            }

                            // データベース通知を作成
                            $lockedPlan->user->notify(
                                new ReadingPlanReminder(
                                    $lockedPlan,
                                    $reminder['type']
                                )
                            );

                            $notificationCount++;
                        });
                    }
                });
        }

        $this->info("作成した通知: {$notificationCount} 件");

        return self::SUCCESS;
    }
}
