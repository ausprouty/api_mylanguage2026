<?php
declare(strict_types=1);

namespace App\Services\BiblePassage;

/**
 * Back-compat shim: some code asks for "BibleBrainLanguageService".
 * It is functionally the same as BibleBrainPassageService.
 */
class BibleBrainLanguageService extends BibleBrainPassageService
{
}
