<?php

namespace App\Services\Bible;

class PassageFormatterService
{
    public function formatPassageText($verses)
    {
        $text = '';
        $multiVerseLine = false;
        $startVerseNumber = null;

        foreach ($verses as $verse) {
            if (empty($verse->verse_text)) {
                return null; // Return null if no text
            }

            $verseNum = $verse->verse_start_alt;
            if ($multiVerseLine) {
                $multiVerseLine = false;
                $verseNum = $startVerseNumber . '-' . $verse->verse_end_alt;
            }
            if ($verse->verse_text === '-') {
                $multiVerseLine = true;
                $startVerseNumber = $verse->verse_start_alt;
            } else {
                $text .= '<p><sup class="versenum">' . $verseNum . '</sup> ' . $verse->verse_text . '</p>';
            }
        }

        return $text ?: null;
    }
}
