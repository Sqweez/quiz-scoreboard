<?php

namespace App\Rules;

use App\Services\QuizPleaseRatingClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RatingCategory implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = array_column(app(QuizPleaseRatingClient::class)->categories(), 'id');

        if (!in_array($value, $ids, true)) {
            $fail('Такой категории рейтинга нет.');
        }
    }
}
