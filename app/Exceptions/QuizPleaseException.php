<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class QuizPleaseException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function authRequired(): self
    {
        return new self('auth_required', 'Войдите в «Квиз, плиз!», чтобы получить игру.', 409);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'Неверный логин или пароль «Квиз, плиз!».', 422);
    }

    public static function forbidden(): self
    {
        return new self('forbidden', 'У вашего аккаунта «Квиз, плиз!» нет доступа к этой игре.', 403);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'Игра с таким ID не найдена в «Квиз, плиз!».', 404);
    }

    public static function unavailable(): self
    {
        return new self('unavailable', 'Сервис «Квиз, плиз!» не отвечает или вернул ошибку. Попробуйте позже.', 502);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], $this->status);
    }
}
