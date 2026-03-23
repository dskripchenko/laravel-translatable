<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Events;

use Dskripchenko\LaravelTranslatable\Models\Translation;
use Illuminate\Foundation\Events\Dispatchable;

class TranslationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Translation $translation,
        public readonly string $oldContent,
    ) {}
}
