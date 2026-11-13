<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'externalId' => ['nullable', 'uuid', Rule::unique('games', 'external_id')->where('user_id', $this->user()->id)],
            'teamNames' => ['required', 'array', 'min:1'],
            'teamNames.*' => ['required', 'string', 'distinct:ignore_case'],
            'rounds' => ['required', 'array', 'min:1', 'max:30'],
            'rounds.*.title' => ['required', 'string'],
            'rounds.*.maxScore' => ['nullable', 'numeric'],
            'rounds.*.questionsCount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'externalId.unique' => 'Игра из «Квиз, плиз!» с этим ID уже создана.',
        ];
    }
}
