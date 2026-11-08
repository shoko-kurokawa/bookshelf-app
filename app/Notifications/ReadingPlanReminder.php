<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    public function __construct(
        private ReadingPlan $readingPlan,
        private string $reminderType
    ) {
    }

    /**
     * 通知方法：データベースのみ
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * データベースに保存する通知内容
     */
    public function toDatabase(object $notifiable): array
    {
        $messages = [
            'before_3_days' => '読了目標日まであと3日です。',
            'on_target_date' => '今日は読了目標日です。',
            'after_3_days' => '読了目標日から3日が経過しました。',
        ];

        $timings = [
            'before_3_days' => 'three_days_before',
            'on_target_date' => 'on_due_date',
            'after_3_days' => 'three_days_after',
        ];

        return [
            'reading_plan_id' => $this->readingPlan->id,

            // 提供されたBladeが参照するキー
            'title' => $this->readingPlan->book->title,
            'body' => $messages[$this->reminderType],
            'timing' => $timings[$this->reminderType],

            // 今後の拡張に備えて保持
            'target_date' => $this->readingPlan->target_date->format('Y-m-d'),
            'url' => route('reading-plans.index'),
        ];
    }
}