<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'teamNames' => ['required', 'array', 'min:1'],
            'teamNames.*' => ['required', 'string'],
            'rounds' => ['required', 'array', 'min:1'],
            'rounds.*.title' => ['required', 'string'],
            'rounds.*.maxScore' => ['nullable', 'numeric'],
            'rounds.*.questionsCount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
