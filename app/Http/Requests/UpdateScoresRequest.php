<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoresRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'updates' => ['present', 'array'],
            'updates.*.teamId' => ['required', 'uuid'],
            'updates.*.roundId' => ['required', 'uuid'],
            'updates.*.score' => ['present', 'nullable', 'numeric'],
        ];
    }
}
