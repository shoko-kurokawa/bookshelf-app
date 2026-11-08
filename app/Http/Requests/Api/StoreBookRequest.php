<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 認証はルートのauth:sanctumで行う
        return true;
    }

    public function rules(): array
    {
        return [
            // user_idは受け取らず、Controllerで認証ユーザーから取得
            'user_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'size:13',
                Rule::unique('books', 'isbn'),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => [
                'integer',
                'distinct',
                Rule::exists('genres', 'id'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ユーザーID',
            'title' => 'タイトル',
            'author' => '著者',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
            'genres.*' => 'ジャンル',
        ];
    }
}
