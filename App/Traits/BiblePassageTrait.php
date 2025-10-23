<?php

namespace App\Traits;

use App\Models\Bible\PassageModel;

trait BiblePassageTrait
{
    /**
     * Creates a Bible passage ID from a Bible reference model.
     *
     * @param PassageModel $passage The Bible reference model.
     * @return string The generated Bible passage ID.
     */
    public static function createPassageId(
        PassageModel $passage
    ): string {
        return $passage->getBookID() . '-' .
            $passage->getChapterStart() . '-' .
            $passage->getVerseStart() . '-' .
            $passage->getVerseEnd();
    }
}
