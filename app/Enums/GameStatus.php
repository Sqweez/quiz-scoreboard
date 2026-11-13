<?php

namespace App\Enums;

enum GameStatus: string
{
    case Draft = 'draft';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Открытая',
            self::Finished => 'Завершенная',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Draft => 'Открыть',
            self::Finished => 'Просмотр',
        };
    }
}
