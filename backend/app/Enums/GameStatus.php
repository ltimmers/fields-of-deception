<?php

namespace App\Enums;

enum GameStatus: string
{
    case WAITING = 'waiting';
    case SETUP = 'setup';
    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
    case ABANDONED = 'abandoned';
}
