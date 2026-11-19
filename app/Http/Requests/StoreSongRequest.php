<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'original_key' => ['required', 'string', 'max:10'],
            'bpm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'time_signature' => ['nullable', 'string', 'max:10'],
            'capo' => ['nullable', 'integer', 'min:0', 'max:12'],
            'content' => ['required', 'string'],
            'structure' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ];
    }
}
