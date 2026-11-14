<?php

namespace App\Http\Requests;

use App\Rules\RatingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'ratingPackage' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    // A linked game keeps its Quiz Please ID: changing or clearing it would orphan the rating data built on it.
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $linkedId = $this->route('game')->external_id;

                if ($linkedId !== null && $this->has('externalId') && strtolower((string) $this->input('externalId')) !== $linkedId) {
                    $validator->errors()->add('externalId', 'Игра уже привязана к «Квиз, плиз!», ID изменить нельзя.');
                }
            },
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
