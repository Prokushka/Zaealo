<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class InsufficientZarks extends Exception implements ShouldntReport
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct('Недостаточно ZARQ для выполнения операции.');
    }
}
