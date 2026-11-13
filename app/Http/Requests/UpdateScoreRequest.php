<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teamId' => ['required', 'uuid'],
            'roundId' => ['required', 'uuid'],
            'score' => ['present', 'nullable', 'numeric'],
        ];
    }
}
