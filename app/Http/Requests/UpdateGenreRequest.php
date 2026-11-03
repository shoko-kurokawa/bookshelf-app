<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $genre = $this->route('genre');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($genre->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ジャンル名',
        ];
    }
}
