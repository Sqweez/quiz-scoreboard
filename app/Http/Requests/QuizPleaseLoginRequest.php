<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuizPleaseLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
