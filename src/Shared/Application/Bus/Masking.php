<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

enum Masking
{
    case Hide;
    case KeepFirstThree;
}
