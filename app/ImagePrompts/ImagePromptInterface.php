<?php

declare(strict_types=1);

namespace App\ImagePrompts;

interface ImagePromptInterface
{
    public function generate(): string;
}
