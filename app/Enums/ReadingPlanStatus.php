<?php

namespace App\Enums;

enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * 画面表示用の日本語ラベル
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '読書中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }
}
