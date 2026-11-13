<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoundRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
        ];
    }
}
