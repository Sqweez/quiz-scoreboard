<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuizPleaseGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Введите ID игры.',
            'id.uuid' => 'ID игры должен быть в формате UUID, например 01a19b42-0cc8-709f-8197-62881b0dbcdc.',
        ];
    }
}
