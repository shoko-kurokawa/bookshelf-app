<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $readingPlan = $this->route('readingPlan');

        return $this->user() !== null
            && $readingPlan instanceof ReadingPlan
            && $readingPlan->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'target_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $readingPlan = $this->route('readingPlan');

                if (!$readingPlan instanceof ReadingPlan) {
                    return;
                }

                if ($readingPlan->status === ReadingPlanStatus::Completed) {
                    $validator->errors()->add(
                        'target_date',
                        '読了済みの計画は編集できません。'
                    );

                    return;
                }

                if (
                    $readingPlan->status === ReadingPlanStatus::Expired
                    && $this->input('target_date') !== $readingPlan->target_date->format('Y-m-d')
                ) {
                    $exists = ReadingPlan::query()
                        ->where('user_id', $this->user()->id)
                        ->where('book_id', $readingPlan->book_id)
                        ->where('status', ReadingPlanStatus::InProgress->value)
                        ->whereKeyNot($readingPlan->id)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            'target_date',
                            'この書籍には、すでに読書中の計画があります。'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'target_date.required' => '目標日を入力してください。',
            'target_date.date_format' => '目標日は正しい日付で入力してください。',
            'target_date.after_or_equal' => '目標日は本日以降の日付を指定してください。',
        ];
    }
}
