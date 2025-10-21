<?php

namespace App\Helpers;

/**
 * Utility class for parsing content related to Bible passages.
 */
class YouVersionContentParser
{
    /**
     * Extracts content before the specified chapter and verse in the given text.
     *
     * @param string $webpageContent The full content of the webpage.
     * @param string $chapterAndVerse The chapter and verse to search for (e.g., "3:16").
     * @return string|null The content before the chapter and verse, or null if not found.
     */
    public static function extractContentBeforeVerse(
        string $webpageContent,
        string $chapterAndVerse
    ): ?string {
        $endPosition = strpos($webpageContent, $chapterAndVerse);
        return $endPosition !== false 
            ? substr($webpageContent, 0, $endPosition) 
            : null;
    }

    /**
     * Parses and retrieves the book name from the given content.
     *
     * @param string $content The content from which to extract the book name.
     * @return string The parsed book name.
     */
    public static function parseBookNameFromContent(string $content): string
    {
        $start = strrpos($content, '"') + 1;
        return trim(substr($content, $start));
    }
}
