<?php

namespace App\Http\Requests;

use App\Rules\RatingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string'],
            'externalId' => [
                'sometimes', 'nullable', 'uuid',
                Rule::unique('games', 'external_id')->where('user_id', $this->user()->id)->ignore($this->route('game')),
            ],
            'ratingCategoryId' => ['sometimes', 'nullable', 'integer', new RatingCategory],
            'ratingIncludeScores' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'externalId.uuid' => 'ID игры в Квиз, плиз! должен быть UUID.',
            'externalId.unique' => 'Игра из «Квиз, плиз!» с этим ID уже создана.',
        ];
    }
}
