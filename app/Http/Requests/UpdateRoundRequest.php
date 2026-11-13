<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoundRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string'],
            'maxScore' => ['sometimes', 'nullable', 'numeric'],
            'questionsCount' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
