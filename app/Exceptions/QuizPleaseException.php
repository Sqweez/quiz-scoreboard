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

    public static function ratingUnavailable(): self
    {
        return new self('rating_unavailable', 'Рейтинг «Квиз, плиз!» не отвечает. Попробуйте позже.', 502);
    }

    // The rating API explains rejections in "message"; it is passed on so the user can fix the game.
    public static function ratingRejected(?string $message): self
    {
        return new self('rating_rejected', $message ?: 'Рейтинг «Квиз, плиз!» отклонил игру.', 422);
    }

    public static function ratingIncomplete(): self
    {
        return new self('rating_incomplete', 'Для рейтинга игра должна быть завершена, привязана к «Квиз, плиз!» и иметь категорию рейтинга.', 422);
    }

    public static function ratingAlreadyCreated(): self
    {
        return new self('rating_already_created', 'Игра уже создана в рейтинге «Квиз, плиз!».', 409);
    }

    public static function ratingAlreadyUploaded(): self
    {
        return new self('rating_already_uploaded', 'Результаты игры уже отправлены в рейтинг «Квиз, плиз!».', 409);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], $this->status);
    }
}
