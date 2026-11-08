<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $book = $this->route('book');

        // 所有者以外は403エラー
        return $book !== null
            && $this->user() !== null
            && (int) $book->user_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        $book = $this->route('book');

        return [
            // 書籍の所有者変更は禁止
            'user_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'size:13',
                Rule::unique('books', 'isbn')->ignore($book->id),
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
