<?php

namespace App\Domain\SchedulingOperations\Enums;

enum WalkInStatus: string
{
    case Waiting = 'waiting';
    case Notified = 'notified';
    case Assigned = 'assigned';
    case InService = 'in_service';
    case Completed = 'completed';
    case Left = 'left';
}
