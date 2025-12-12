<?php

declare(strict_types=1);

namespace App\Services\Language;

use App\Models\Bible\BibleModel;

class TextDirectionService
{
    public function determineDirectionForBible(
        BibleModel $bible
    ): string {
        // use LanguageRepository + DB updates here
    }

    public function wrapPassageHtml(
        string $html,
        string $direction
    ): string {
        if ($html === '') {
            return '';
        }

        return sprintf('<div dir="%s">%s</div>', $direction, $html);
    }
}
